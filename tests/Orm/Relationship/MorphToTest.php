<?php
/*
 * This file is part of Hector ORM.
 *
 * @license   https://opensource.org/licenses/MIT MIT License
 * @copyright 2026 Ronan GIRON
 * @author    Ronan GIRON <https://github.com/ElGigi>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code, to the root.
 */

declare(strict_types=1);

namespace Hector\Orm\Tests\Relationship;

use Hector\Connection\Connection;
use Hector\Connection\Log\Logger;
use Hector\Orm\Attributes as OrmAttribute;
use Hector\Orm\Collection\Collection;
use Hector\Orm\Entity\MagicEntity;
use Hector\Orm\Entity\ReflectionEntity;
use Hector\Orm\Exception\RelationException;
use Hector\Orm\Orm;
use Hector\Orm\Relationship\MorphTo;
use Hector\Schema\Generator\Sqlite;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

class MorphToTest extends TestCase
{
    private const COLUMNS = ['commentable_id' => 'id'];

    private Connection $connection;
    private Orm $orm;
    private ?Orm $previousOrm;
    private array $previousReflections;

    protected function setUp(): void
    {
        $this->previousOrm = Orm::$instance;
        Orm::$instance = null;
        $reflections = new ReflectionProperty(ReflectionEntity::class, 'reflections');
        PHP_VERSION_ID < 80100 && $reflections->setAccessible(true);
        $this->previousReflections = $reflections->getValue();
        $reflections->setValue(null, []);

        $this->connection = new Connection('sqlite::memory:', logger: new Logger());
        foreach ([
            'CREATE TABLE morphto_article (id INTEGER PRIMARY KEY AUTOINCREMENT, title TEXT)',
            'CREATE TABLE morphto_video (id INTEGER PRIMARY KEY AUTOINCREMENT, title TEXT)',
            'CREATE TABLE morphto_page (id INTEGER PRIMARY KEY AUTOINCREMENT, legacy_id INTEGER UNIQUE, title TEXT)',
            'CREATE TABLE morphto_comment (id INTEGER PRIMARY KEY AUTOINCREMENT, commentable_type TEXT, '
                . 'commentable_id INTEGER, body TEXT, reviewer_type TEXT, reviewer_id INTEGER)',
            'CREATE TABLE morphto_note (id INTEGER PRIMARY KEY AUTOINCREMENT, owner_kind INTEGER, '
                . 'owner_id INTEGER, body TEXT)',
        ] as $sql) {
            $this->connection->execute($sql);
        }

        // Articles and videos share identifiers: only the discriminator tells which one a comment refers to.
        $this->connection->execute("INSERT INTO morphto_article VALUES (1, 'first article'), (2, 'second article')");
        $this->connection->execute("INSERT INTO morphto_video VALUES (1, 'first video'), (2, 'second video')");
        $this->connection->execute("INSERT INTO morphto_page VALUES (10, 1, 'first page')");
        $this->connection->execute(
            'INSERT INTO morphto_comment (id, commentable_type, commentable_id, body) VALUES '
            . "(1, 'article', 1, 'a1-first'), (2, 'article', 1, 'a1-second'), "
            . "(3, 'video', 1, 'v1-first'), (4, 'article', 2, 'a2-first'), (5, NULL, NULL, 'unowned'), "
            . "(6, 'page', 1, 'p1-first'), (7, 'article', 99, 'dangling'), (8, 'unknown', 1, 'unknown type')",
        );
        $this->connection->execute("UPDATE morphto_comment SET reviewer_type = 'video', reviewer_id = 2 WHERE id = 1");
        // Integer discriminator: articles are kind 0, the value most easily lost in a loose comparison.
        $this->connection->execute(
            "INSERT INTO morphto_note VALUES (1, 0, 1, 'article note'), (2, 1, 1, 'video note'), "
            . "(3, NULL, NULL, 'unowned')",
        );

        $this->orm = new Orm($this->connection, (new Sqlite($this->connection))->generateSchemas('main'));
    }

    protected function tearDown(): void
    {
        Orm::$instance = $this->previousOrm;
        $reflections = new ReflectionProperty(ReflectionEntity::class, 'reflections');
        PHP_VERSION_ID < 80100 && $reflections->setAccessible(true);
        $reflections->setValue(null, $this->previousReflections);
    }

    public function testSharedNameResolvesToTheTargetNamedByTheDiscriminator(): void
    {
        $this->assertTarget(ToArticle::class, 1, ToComment::find(1)->commentable);
        $this->assertTarget(ToVideo::class, 1, ToComment::find(3)->commentable);
        $this->assertTarget(ToPage::class, 10, ToComment::find(6)->commentable);
    }

    public function testRowWithoutResolvableTargetIsNull(): void
    {
        $this->assertNull(ToComment::find(5)->commentable, 'no type and no key');
        $this->assertNull(ToComment::find(7)->commentable, 'key without a matching target row');
        $this->assertNull(ToComment::find(8)->commentable, 'type unknown to the relationship');
    }

    public function testOwnNameNeverBorrowsATargetOfAnotherType(): void
    {
        $articleComment = ToComment::find(1);
        $this->assertTarget(ToArticle::class, 1, $articleComment->article);
        $this->assertNull($articleComment->video);

        $videoComment = ToComment::find(3);
        $this->assertNull($videoComment->article);
        $this->assertTarget(ToVideo::class, 1, $videoComment->video);
    }

    public function testEagerLoadingQueriesEachTargetTypeAndSharesInstances(): void
    {
        $queries = count($this->connection->getLogger());
        $comments = ToComment::query()->orderBy('id')->with(['commentable'])->all();
        $this->assertSame(4, count($this->connection->getLogger()) - $queries, 'comments, then one per type');

        $comments->load(['article', 'video']);

        $this->assertTarget(ToArticle::class, 1, $comments[0]->commentable);
        $this->assertSame($comments[0]->commentable, $comments[1]->commentable);
        $this->assertTarget(ToVideo::class, 1, $comments[2]->commentable);
        $this->assertTarget(ToArticle::class, 2, $comments[3]->commentable);
        $this->assertNull($comments[4]->commentable);
        $this->assertTarget(ToPage::class, 10, $comments[5]->commentable);
        $this->assertNull($comments[6]->commentable);
        $this->assertNull($comments[7]->commentable);

        $this->assertTarget(ToArticle::class, 1, $comments[0]->article);
        $this->assertNull($comments[0]->video);
        $this->assertNull($comments[2]->article);
        $this->assertTarget(ToVideo::class, 1, $comments[2]->video);
    }

    public function testAssignmentWritesKeyAndDiscriminator(): void
    {
        $comment = new ToComment();
        $comment->body = 'new';
        $comment->commentable = ToVideo::find(2);
        $comment->save();

        $this->assertSame(['commentable_type' => 'video', 'commentable_id' => 2], $this->link($comment->id));
        $this->assertNull($comment->getRelated()->getAssignment('commentable'));

        $existing = ToComment::find(1);
        $existing->commentable = ToPage::find(10);
        $existing->save();

        $this->assertSame(['commentable_type' => 'page', 'commentable_id' => 1], $this->link(1));
        $this->assertTarget(ToPage::class, 10, ToComment::find(1)->commentable);
    }

    public function testAssignmentThroughOwnNameWritesKeyAndDiscriminator(): void
    {
        $comment = ToComment::find(5);
        $comment->article = ToArticle::find(2);
        $comment->save();

        $this->assertSame(['commentable_type' => 'article', 'commentable_id' => 2], $this->link(5));
    }

    public function testAssignedNewTargetIsSavedFirst(): void
    {
        $article = new ToArticle();
        $article->title = 'new article';
        $comment = new ToComment();
        $comment->body = 'new';
        $comment->commentable = $article;
        $comment->save();

        $this->assertNotNull($article->id);
        $this->assertSame(
            ['commentable_type' => 'article', 'commentable_id' => $article->id],
            $this->link($comment->id),
        );
    }

    public function testNullAssignmentClearsTheKeyAndKeepsTheDiscriminator(): void
    {
        $comment = ToComment::find(1);
        $this->assertNotNull($comment->commentable);
        $comment->commentable = null;
        $comment->save();

        $this->assertSame(['commentable_type' => 'article', 'commentable_id' => null], $this->link(1));
        $this->assertNull(ToComment::find(1)->commentable);
    }

    public function testNullAssignmentDoesNotClearARowOfAnotherType(): void
    {
        $comment = ToComment::find(3);
        $comment->article = null;
        $comment->save();

        $this->assertSame(['commentable_type' => 'video', 'commentable_id' => 1], $this->link(3));
    }

    public function testLoadedValuesAreNeverWrittenBack(): void
    {
        // A dangling reference read as null must survive a save of its row.
        $dangling = ToComment::find(7);
        $this->assertNull($dangling->commentable);
        $dangling->body = 'edited';
        $dangling->save();
        $this->assertSame(['commentable_type' => 'article', 'commentable_id' => 99], $this->link(7));

        // Relationships sharing the columns: the one loaded as null must not clear the key of the other.
        $comment = ToComment::find(1);
        $this->assertNotNull($comment->article);
        $this->assertNull($comment->video);
        $comment->body = 'edited';
        $comment->save();
        $this->assertSame(['commentable_type' => 'article', 'commentable_id' => 1], $this->link(1));
    }

    public function testAssignmentPrevailsOverALoadedSibling(): void
    {
        foreach ([true, false] as $loadedFirst) {
            $this->connection->execute(
                "UPDATE morphto_comment SET commentable_type = 'article', commentable_id = 1 WHERE id = 1",
            );
            $comment = ToComment::find(1);
            true === $loadedFirst && $this->assertNotNull($comment->article);
            $comment->video = ToVideo::find(2);
            false === $loadedFirst && $this->assertNotNull($comment->article);
            $comment->save();

            $this->assertSame(['commentable_type' => 'video', 'commentable_id' => 2], $this->link(1));
        }
    }

    public function testInvalidTargetIsRejected(): void
    {
        $comment = ToComment::find(1);

        try {
            $comment->commentable = new ToComment();
            $this->fail('A comment was accepted as its own target');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString(ToArticle::class . '|' . ToVideo::class, $exception->getMessage());
        }

        $this->expectException(InvalidArgumentException::class);
        $comment->article = ToVideo::find(1);
    }

    public function testIntegerZeroDiscriminatorRoundTrips(): void
    {
        // Lazy resolution
        $this->assertTarget(ToArticle::class, 1, ToNote::find(1)->owner);
        $this->assertTarget(ToVideo::class, 1, ToNote::find(2)->owner);
        $this->assertNull(ToNote::find(3)->owner);
        $this->assertTarget(ToArticle::class, 1, ToNote::find(1)->article);
        $this->assertNull(ToNote::find(2)->article);

        // Eager resolution and join
        $notes = ToNote::query()->orderBy('id')->with(['owner'])->all();
        $this->assertTarget(ToArticle::class, 1, $notes[0]->owner);
        $this->assertTarget(ToVideo::class, 1, $notes[1]->owner);
        $this->assertNull($notes[2]->owner);
        $this->assertSame([1], $this->ids(ToNote::query()->where('article.title', 'first article')->all()));

        // Assignment
        $note = ToNote::find(3);
        $note->owner = ToArticle::find(2);
        $note->save();
        $this->assertSame(['owner_kind' => 0, 'owner_id' => 2], $this->noteLink(3));

        // Null assignment needs the type of the row to be resolved
        $note = ToNote::find(1);
        $note->owner = null;
        $note->save();
        $this->assertSame(['owner_kind' => 0, 'owner_id' => null], $this->noteLink(1));
        $this->assertSame(['owner_kind' => 1, 'owner_id' => 1], $this->noteLink(2));
    }

    public function testTargetAccessors(): void
    {
        $relationships = $this->orm->getMapper(ToComment::class)->getRelationships();
        $shared = $relationships->get('commentable');
        $own = $relationships->get('article');

        $this->assertInstanceOf(MorphTo::class, $shared);
        $this->assertSame([ToArticle::class, ToVideo::class, ToPage::class], $shared->getTargetEntities());
        $this->assertSame(['commentable_type'], $shared->getDiscriminatorColumns());
        $this->assertSame(['commentable_id'], $shared->getSourceColumns());
        $this->assertSame([], $shared->getDiscriminator());
        $this->assertSame(ToArticle::class, $own->getTargetEntity());
        $this->assertSame(['id'], $own->getTargetColumns());

        foreach (['getTargetEntity', 'getTargetColumns'] as $method) {
            try {
                $shared->{$method}();
                $this->fail(sprintf('%s() answered for several targets', $method));
            } catch (RelationException $exception) {
                $this->assertStringContainsString('several target entities', $exception->getMessage());
            }
        }
    }

    public function testBuilderTargetsTheTypeOfTheEntity(): void
    {
        $this->assertSame([1], $this->ids(ToComment::find(1)->getRelated()->getBuilder('commentable')->all()));
        $this->assertSame([10], $this->ids(ToComment::find(6)->getRelated()->getBuilder('commentable')->all()));
        $this->assertSame([], $this->ids(ToComment::find(3)->getRelated()->getBuilder('article')->all()));
        $this->assertSame([], $this->ids(ToComment::find(7)->getRelated()->getBuilder('commentable')->all()));

        $relationship = $this->orm->getMapper(ToComment::class)->getRelationships()->get('commentable');
        foreach ([[ToComment::find(5)], [ToComment::find(1), ToComment::find(3)]] as $comments) {
            try {
                $relationship->getBuilder(...$comments);
                $this->fail('A single query was built without a single target');
            } catch (RelationException $exception) {
                $this->assertStringContainsString('several target entities', $exception->getMessage());
            }
        }
    }

    public function testParentLinkInvalidatesCachedReferenceAndPendingAssignment(): void
    {
        $comment = ToComment::find(3);
        $this->assertTarget(ToVideo::class, 1, $comment->commentable);
        $comment->commentable = ToVideo::find(2);

        $article = ToArticle::find(2);
        $article->comments[] = $comment;
        $article->save();

        $this->assertSame(['commentable_type' => 'article', 'commentable_id' => 2], $this->link(3));
        $this->assertNull($comment->getRelated()->getAssignment('commentable'));
        $this->assertTarget(ToArticle::class, 2, $comment->commentable);

        $comment->body = 'edited';
        $comment->save();
        $this->assertSame(['commentable_type' => 'article', 'commentable_id' => 2], $this->link(3));
    }

    public function testParentDetachmentInvalidatesCachedReference(): void
    {
        $article = ToArticle::find(1);
        $comments = $article->comments;
        $comment = $comments[0];
        $this->assertTarget(ToArticle::class, 1, $comment->commentable);
        unset($comments[0]);
        $article->save();

        $this->assertSame(['commentable_type' => 'article', 'commentable_id' => null], $this->link($comment->id));
        $this->assertNull($comment->commentable);
    }

    public function testProgrammaticDeclarationAddsTargetsUnderTheSameName(): void
    {
        $relationships = $this->orm->getMapper(ToComment::class)->getRelationships();
        $relationship = $relationships->morphTo(
            ToVideo::class,
            'subject',
            self::COLUMNS,
            ['commentable_type' => 'video'],
        );

        $this->assertSame(
            $relationship,
            $relationships->morphTo(ToArticle::class, 'subject', self::COLUMNS, ['commentable_type' => 'article']),
        );
        $this->assertSame([ToVideo::class, ToArticle::class], $relationship->getTargetEntities());
        $this->assertTarget(ToArticle::class, 1, ToComment::find(1)->subject);
        $this->assertTarget(ToVideo::class, 1, ToComment::find(3)->subject);
    }

    /**
     * @dataProvider invalidTargetProvider
     */
    public function testInconsistentTargetIsRejected(
        string $target,
        array $columns,
        array $discriminator,
        string $message,
    ): void {
        $relationships = $this->orm->getMapper(ToComment::class)->getRelationships();
        $relationship = $relationships->morphTo(
            ToArticle::class,
            'subject',
            self::COLUMNS,
            ['commentable_type' => 'a'],
        );

        try {
            $relationships->morphTo($target, 'subject', $columns, $discriminator);
            $this->fail('An inconsistent target was accepted');
        } catch (RelationException $exception) {
            $this->assertStringContainsString($message, $exception->getMessage());
        }

        $this->assertSame([ToArticle::class], $relationship->getTargetEntities());
    }

    public static function invalidTargetProvider(): array
    {
        return [
            'missing columns' => [ToVideo::class, [], ['commentable_type' => 'v'], 'requires explicit columns'],
            'missing discriminator' => [ToVideo::class, self::COLUMNS, [], 'requires a discriminator'],
            'unnamed discriminator column' => [
                ToVideo::class,
                self::COLUMNS,
                ['v'],
                'column names to string or integer values',
            ],
            'null discriminator value' => [
                ToVideo::class,
                self::COLUMNS,
                ['commentable_type' => null],
                'string or integer values',
            ],
            'boolean discriminator value' => [
                ToVideo::class,
                self::COLUMNS,
                ['commentable_type' => false],
                'string or integer values',
            ],
            'float discriminator value' => [
                ToVideo::class,
                self::COLUMNS,
                ['commentable_type' => 1.5],
                'string or integer values',
            ],
            'discriminator on a key column' => [
                ToVideo::class,
                self::COLUMNS,
                ['commentable_id' => 1],
                'already a key column',
            ],
            'other source columns' => [
                ToVideo::class,
                ['body' => 'id'],
                ['commentable_type' => 'v'],
                'same source columns',
            ],
            'other discriminator columns' => [
                ToVideo::class,
                self::COLUMNS,
                ['body' => 'v'],
                'same discriminator columns',
            ],
            'target declared twice' => [
                ToArticle::class,
                self::COLUMNS,
                ['commentable_type' => 'b'],
                'already targets',
            ],
            'discriminator declared twice' => [
                ToVideo::class,
                self::COLUMNS,
                ['commentable_type' => 'a'],
                'share the same discriminator',
            ],
        ];
    }

    public function testNameCannotBeSharedWithAnotherKindOfRelationship(): void
    {
        foreach ([ToMixedFirst::class, ToMixedLast::class] as $entity) {
            try {
                $this->orm->getMapper($entity)->getRelationships();
                $this->fail(sprintf('%s mixes a polymorphic relationship with another kind', $entity));
            } catch (RelationException $exception) {
                $this->assertStringContainsString('polymorphic and of another kind', $exception->getMessage());
            }
        }
    }

    public function testPolymorphicReferenceCannotBeInverted(): void
    {
        $relationships = $this->orm->getMapper(ToArticle::class)->getRelationships();

        foreach (['belongsTo', 'inverseOf'] as $method) {
            try {
                $relationships->{$method}(ToComment::class, 'inverted', 'commentable');
                $this->fail(sprintf('%s() inverted a polymorphic reference', $method));
            } catch (RelationException $exception) {
                $this->assertStringContainsString('cannot be inverted', $exception->getMessage());
            }
        }

        $this->assertFalse($relationships->exists('inverted'));
    }

    public function testAssignmentInvalidatesSiblingsSharingItsColumns(): void
    {
        $comment = ToComment::find(1);
        $this->assertTarget(ToArticle::class, 1, $comment->article);
        $this->assertTarget(ToArticle::class, 1, $comment->commentable);
        $this->assertTarget(ToVideo::class, 2, $comment->reviewer);

        $comment->video = ToVideo::find(2);

        $this->assertFalse($comment->getRelated()->isset('article'));
        $this->assertFalse($comment->getRelated()->isset('commentable'));
        $this->assertTrue($comment->getRelated()->isset('reviewer'), 'other columns: not a sibling');

        $comment->save();

        $this->assertSame(['commentable_type' => 'video', 'commentable_id' => 2], $this->link(1));
        $this->assertNull($comment->article);
        $this->assertTarget(ToVideo::class, 2, $comment->commentable);
        $this->assertTarget(ToVideo::class, 2, $comment->video);
    }

    public function testLastAssignmentAmongSiblingsPrevails(): void
    {
        $comment = ToComment::find(5);
        $comment->article = ToArticle::find(1);
        $comment->video = ToVideo::find(2);
        $comment->article = ToArticle::find(2);

        $this->assertNull($comment->getRelated()->getAssignment('video'));
        $comment->save();

        $this->assertSame(['commentable_type' => 'article', 'commentable_id' => 2], $this->link(5));
    }

    public function testNestedLoadingOnTargetsOfSeveralClasses(): void
    {
        $comments = ToComment::query()->orderBy('id')->with(['commentable' => ['comments']])->all();

        $this->assertSame([1, 2], $this->ids($comments[0]->commentable->getRelated()->get('comments')));
        $this->assertTrue($comments[0]->commentable->getRelated()->isset('comments'));
        $this->assertTrue($comments[2]->commentable->getRelated()->isset('comments'));
        $this->assertSame([3], $this->ids($comments[2]->commentable->comments));
        $this->assertFalse($comments[5]->commentable->getRelated()->isset('comments'), 'pages have no comments');
    }

    public function testNestedRelationshipMustExistOnAtLeastOneClass(): void
    {
        $this->expectException(RelationException::class);
        $this->expectExceptionMessage('Relationship "unknown" not found');

        ToComment::query()->with(['commentable' => ['unknown']])->all();
    }

    public function testSubclassLoadsWithTheClassItExtends(): void
    {
        $featured = new ToFeaturedArticle();
        $featured->title = 'featured';
        $featured->save();
        $comment = ToComment::find(5);
        $comment->commentable = $featured;
        $comment->save();
        $this->assertSame(['commentable_type' => 'article', 'commentable_id' => $featured->id], $this->link(5));

        $articles = new Collection([ToArticle::find(1), $featured]);
        $queries = count($this->connection->getLogger());
        $articles->load(['comments']);

        $this->assertSame(1, count($this->connection->getLogger()) - $queries);
        $this->assertSame([1, 2], $this->ids($articles[0]->comments));
        $this->assertSame([5], $this->ids($articles[1]->comments));
    }

    public function testConditionThroughOwnNameJoinsOnTheDiscriminator(): void
    {
        $this->assertSame([1, 2], $this->ids(ToComment::query()->where('article.title', 'first article')->all()));
        $this->assertSame([3], $this->ids(ToComment::query()->where('video.title', 'first video')->all()));
        $this->assertSame([], $this->ids(ToComment::query()->where('video.title', 'first article')->all()));
        $this->assertSame(
            [1],
            $this->ids(
                ToComment::query()
                    ->where('article.title', 'first article')
                    ->where('reviewer.title', 'second video')
                    ->all(),
            ),
        );
    }

    public function testConditionThroughSeveralTargetsIsRejected(): void
    {
        $this->expectException(RelationException::class);
        $this->expectExceptionMessage('cannot be joined');

        ToComment::query()->where('commentable.title', 'first article')->all();
    }

    private function assertTarget(string $class, int $id, mixed $actual): void
    {
        $this->assertInstanceOf($class, $actual);
        $this->assertSame($id, (int)$actual->id);
    }

    private function ids(iterable $entities): array
    {
        $ids = [];
        foreach ($entities as $entity) {
            $ids[] = (int)$entity->id;
        }

        sort($ids);

        return $ids;
    }

    private function noteLink(int $id): array
    {
        $row = $this->connection->fetchOne('SELECT * FROM morphto_note WHERE id = ?', [$id]);

        return [
            'owner_kind' => null === $row['owner_kind'] ? null : (int)$row['owner_kind'],
            'owner_id' => null === $row['owner_id'] ? null : (int)$row['owner_id'],
        ];
    }

    private function link(int $id): array
    {
        $row = $this->connection->fetchOne('SELECT * FROM morphto_comment WHERE id = ?', [$id]);

        return [
            'commentable_type' => $row['commentable_type'],
            'commentable_id' => null === $row['commentable_id'] ? null : (int)$row['commentable_id'],
        ];
    }
}

#[OrmAttribute\Table('morphto_article', 'main')]
#[OrmAttribute\MorphMany(ToComment::class, 'comments', ['id' => 'commentable_id'], ['commentable_type' => 'article'])]
class ToArticle extends MagicEntity
{
}

#[OrmAttribute\Table('morphto_video', 'main')]
#[OrmAttribute\MorphMany(ToComment::class, 'comments', ['id' => 'commentable_id'], ['commentable_type' => 'video'])]
class ToVideo extends MagicEntity
{
}

#[OrmAttribute\Table('morphto_page', 'main')]
class ToPage extends MagicEntity
{
}

#[OrmAttribute\Table('morphto_comment', 'main')]
#[OrmAttribute\MorphTo(ToArticle::class, 'commentable', ['commentable_id' => 'id'], ['commentable_type' => 'article'])]
#[OrmAttribute\MorphTo(ToVideo::class, 'commentable', ['commentable_id' => 'id'], ['commentable_type' => 'video'])]
#[OrmAttribute\MorphTo(
    ToPage::class,
    'commentable',
    ['commentable_id' => 'legacy_id'],
    ['commentable_type' => 'page'],
)]
#[OrmAttribute\MorphTo(ToArticle::class, 'article', ['commentable_id' => 'id'], ['commentable_type' => 'article'])]
#[OrmAttribute\MorphTo(ToVideo::class, 'video', ['commentable_id' => 'id'], ['commentable_type' => 'video'])]
#[OrmAttribute\MorphTo(ToVideo::class, 'reviewer', ['reviewer_id' => 'id'], ['reviewer_type' => 'video'])]
class ToComment extends MagicEntity
{
}

#[OrmAttribute\Table('morphto_note', 'main')]
#[OrmAttribute\MorphTo(ToArticle::class, 'owner', ['owner_id' => 'id'], ['owner_kind' => 0])]
#[OrmAttribute\MorphTo(ToVideo::class, 'owner', ['owner_id' => 'id'], ['owner_kind' => 1])]
#[OrmAttribute\MorphTo(ToArticle::class, 'article', ['owner_id' => 'id'], ['owner_kind' => 0])]
class ToNote extends MagicEntity
{
}

#[OrmAttribute\MorphMany(ToComment::class, 'comments', ['id' => 'commentable_id'], ['commentable_type' => 'article'])]
class ToFeaturedArticle extends ToArticle
{
}

#[OrmAttribute\Table('morphto_comment', 'main')]
#[OrmAttribute\MorphTo(ToArticle::class, 'subject', ['commentable_id' => 'id'], ['commentable_type' => 'article'])]
#[OrmAttribute\HasOne(ToVideo::class, 'subject', ['commentable_id' => 'id'])]
class ToMixedFirst extends MagicEntity
{
}

#[OrmAttribute\Table('morphto_comment', 'main')]
#[OrmAttribute\HasOne(ToVideo::class, 'subject', ['commentable_id' => 'id'])]
#[OrmAttribute\MorphTo(ToArticle::class, 'subject', ['commentable_id' => 'id'], ['commentable_type' => 'article'])]
class ToMixedLast extends MagicEntity
{
}
