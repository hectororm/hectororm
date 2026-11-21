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
use Hector\Orm\Attributes as OrmAttribute;
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

        $this->connection = new Connection('sqlite::memory:');
        foreach ([
            'CREATE TABLE morphto_article (id INTEGER PRIMARY KEY AUTOINCREMENT, title TEXT)',
            'CREATE TABLE morphto_video (id INTEGER PRIMARY KEY AUTOINCREMENT, title TEXT)',
            'CREATE TABLE morphto_page (id INTEGER PRIMARY KEY AUTOINCREMENT, legacy_id INTEGER UNIQUE, title TEXT)',
            'CREATE TABLE morphto_comment (id INTEGER PRIMARY KEY AUTOINCREMENT, commentable_type TEXT, '
                . 'commentable_id INTEGER, body TEXT)',
        ] as $sql) {
            $this->connection->execute($sql);
        }

        // Articles and videos share identifiers: only the discriminator tells which one a comment refers to.
        $this->connection->execute("INSERT INTO morphto_article VALUES (1, 'first article'), (2, 'second article')");
        $this->connection->execute("INSERT INTO morphto_video VALUES (1, 'first video'), (2, 'second video')");
        $this->connection->execute("INSERT INTO morphto_page VALUES (10, 1, 'first page')");
        $this->connection->execute(
            "INSERT INTO morphto_comment VALUES (1, 'article', 1, 'a1-first'), (2, 'article', 1, 'a1-second'), "
            . "(3, 'video', 1, 'v1-first'), (4, 'article', 2, 'a2-first'), (5, NULL, NULL, 'unowned'), "
            . "(6, 'page', 1, 'p1-first'), (7, 'article', 99, 'dangling'), (8, 'unknown', 1, 'unknown type')",
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
        $comments = ToComment::query()->orderBy('id')->with(['commentable', 'article', 'video'])->all();

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
            'unnamed discriminator column' => [ToVideo::class, self::COLUMNS, ['v'], 'column names to scalar values'],
            'non-scalar discriminator value' => [
                ToVideo::class,
                self::COLUMNS,
                ['commentable_type' => null],
                'scalar values',
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
class ToComment extends MagicEntity
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
