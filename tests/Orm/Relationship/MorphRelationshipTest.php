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
use Hector\Orm\Collection\Collection;
use Hector\Orm\Entity\MagicEntity;
use Hector\Orm\Entity\ReflectionEntity;
use Hector\Orm\Exception\RelationException;
use Hector\Orm\Orm;
use Hector\Orm\Relationship\MorphMany;
use Hector\Orm\Relationship\MorphOne;
use Hector\Orm\Storage\EntityStorage;
use Hector\Schema\Generator\Sqlite;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

class MorphRelationshipTest extends TestCase
{
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
            'CREATE TABLE morph_article (id INTEGER PRIMARY KEY AUTOINCREMENT, title TEXT)',
            'CREATE TABLE morph_video (id INTEGER PRIMARY KEY AUTOINCREMENT, title TEXT)',
            'CREATE TABLE morph_comment (id INTEGER PRIMARY KEY AUTOINCREMENT, commentable_type TEXT, '
                . 'commentable_id INTEGER, body TEXT)',
            'CREATE TABLE morph_image (id INTEGER PRIMARY KEY AUTOINCREMENT, imageable_type TEXT, '
                . 'imageable_id INTEGER, url TEXT)',
            'CREATE TABLE morph_required (id INTEGER PRIMARY KEY AUTOINCREMENT, owner_type TEXT NOT NULL, '
                . 'owner_id INTEGER NOT NULL, label TEXT)',
            'CREATE TABLE morph_note (id INTEGER PRIMARY KEY AUTOINCREMENT, owner_kind INTEGER, '
                . 'owner_id INTEGER, body TEXT)',
        ] as $sql) {
            $this->connection->execute($sql);
        }

        // Articles and videos share identifiers: only the discriminator tells their children apart.
        $this->connection->execute("INSERT INTO morph_article VALUES (1, 'first article'), (2, 'second article')");
        $this->connection->execute("INSERT INTO morph_video VALUES (1, 'first video'), (2, 'second video')");
        $this->connection->execute(
            "INSERT INTO morph_comment VALUES (1, 'article', 1, 'a1-first'), (2, 'article', 1, 'a1-second'), "
            . "(3, 'video', 1, 'v1-first'), (4, 'article', 2, 'a2-first'), (5, NULL, NULL, 'unowned')",
        );
        $this->connection->execute(
            "INSERT INTO morph_image VALUES (1, 'article', 1, 'a1.png'), (2, 'video', 1, 'v1.png')",
        );
        $this->connection->execute("INSERT INTO morph_required VALUES (1, 'article', 1, 'required')");
        // Integer discriminator: articles are kind 0, the value most easily lost in a loose comparison.
        $this->connection->execute(
            "INSERT INTO morph_note VALUES (1, 0, 1, 'article note'), (2, 1, 1, 'video note'), (3, 0, 1, 'owned note')",
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

    public function testLazyLoadingOnlyReturnsChildrenOfTheParentType(): void
    {
        $this->assertSame([1, 2], $this->ids(MorphArticle::find(1)->comments));
        $this->assertSame([3], $this->ids(MorphVideo::find(1)->comments));
        $this->assertSame([], $this->ids(MorphVideo::find(2)->comments));
    }

    public function testEagerLoadingOnlyReturnsChildrenOfTheParentType(): void
    {
        $articles = MorphArticle::query()->orderBy('id')->with(['comments', 'image'])->all();

        $this->assertSame([1, 2], $this->ids($articles[0]->comments));
        $this->assertSame([4], $this->ids($articles[1]->comments));
        $this->assertSame(1, $articles[0]->image->id);
        $this->assertNull($articles[1]->image);
    }

    public function testDiscriminatorIsNotAReadFilterThatCanBeReset(): void
    {
        $relationship = $this->orm->getMapper(MorphArticle::class)->getRelationships()->get('comments');
        $relationship->resetWhere();

        $this->assertInstanceOf(MorphMany::class, $relationship);
        $this->assertSame(['commentable_type' => 'article'], $relationship->getDiscriminator());
        $this->assertSame([1, 2], $this->ids(MorphArticle::find(1)->comments));
    }

    public function testAddedChildReceivesKeyAndDiscriminator(): void
    {
        $article = MorphArticle::find(2);
        $comment = new MorphComment();
        $comment->body = 'new';
        $article->comments[] = $comment;
        $article->save();

        $this->assertSame(
            ['commentable_type' => 'article', 'commentable_id' => 2],
            $this->link('morph_comment', $comment->id, 'commentable'),
        );
        $this->assertSame(EntityStorage::STATUS_NONE, $this->orm->getStatus($comment));
        $article->getRelated()->unset('comments');
        $this->assertSame([4, $comment->id], $this->ids($article->comments));
    }

    public function testNewParentSavesItsChildrenWithTheDiscriminator(): void
    {
        $video = new MorphVideo();
        $video->title = 'new video';
        $comment = new MorphComment();
        $comment->body = 'new';
        $video->comments = new Collection([$comment]);
        $video->save();

        $this->assertNotNull($video->id);
        $this->assertSame(
            ['commentable_type' => 'video', 'commentable_id' => $video->id],
            $this->link('morph_comment', $comment->id, 'commentable'),
        );
    }

    public function testChildMovedFromAnotherParentTypeGetsTheNewDiscriminator(): void
    {
        $comment = MorphComment::find(3);
        $article = MorphArticle::find(2);
        $article->comments[] = $comment;
        $article->save();

        $this->assertSame(
            ['commentable_type' => 'article', 'commentable_id' => 2],
            $this->link('morph_comment', 3, 'commentable'),
        );
        $this->assertSame([], $this->ids(MorphVideo::find(1)->comments));
    }

    public function testSavingParentKeepsAlreadyLinkedChildren(): void
    {
        $article = MorphArticle::find(1);
        $comments = $article->comments;
        $article->title = 'renamed';
        $article->save();

        $this->assertSame('renamed', $this->row('morph_article', 1)['title']);
        $this->assertCount(2, $comments);
        foreach ([1, 2] as $id) {
            $this->assertSame(
                ['commentable_type' => 'article', 'commentable_id' => 1],
                $this->link('morph_comment', $id, 'commentable'),
            );
        }
    }

    public function testDetachmentClearsTheKeyAndKeepsTheDiscriminator(): void
    {
        $article = MorphArticle::find(1);
        $comments = $article->comments;
        unset($comments[0]);
        $article->save();

        $this->assertSame(
            ['commentable_type' => 'article', 'commentable_id' => null],
            $this->link('morph_comment', 1, 'commentable'),
        );
        $this->assertSame([2], $this->ids(MorphArticle::find(1)->comments));
        $this->assertSame([3], $this->ids(MorphVideo::find(1)->comments));
    }

    public function testOrphanRemovalDeletesOnlyTheRemovedChild(): void
    {
        $article = MorphArticle::find(1);
        $comments = $article->ownedComments;
        unset($comments[0]);
        $article->save();

        $this->assertNull($this->row('morph_comment', 1));
        $this->assertNotNull($this->row('morph_comment', 2));
        $this->assertNotNull($this->row('morph_comment', 3));
    }

    public function testChildOfAnotherParentTypeCannotBeRemovedThroughTheRelationship(): void
    {
        $article = MorphArticle::find(1);
        $comments = $article->ownedComments;
        $comments->trackDetached(MorphComment::find(3));

        try {
            $article->save();
            $this->fail('A video comment was removed through an article relationship');
        } catch (RelationException $exception) {
            $this->assertStringContainsString('original parent relationship', $exception->getMessage());
        }

        $this->assertNotNull($this->row('morph_comment', 3));
    }

    public function testRequiredColumnsRejectDetachmentWithoutOrphanRemoval(): void
    {
        $article = MorphArticle::find(1);
        $required = $article->required;
        unset($required[0]);

        try {
            $article->save();
            $this->fail('A required polymorphic key was cleared');
        } catch (RelationException $exception) {
            $this->assertStringContainsString('orphanRemoval: true', $exception->getMessage());
        }

        $this->assertSame(
            ['owner_type' => 'article', 'owner_id' => 1],
            $this->link('morph_required', 1, 'owner'),
        );
    }

    public function testSingleChildIsResolvedPerParentType(): void
    {
        $this->assertSame(1, MorphArticle::find(1)->image->id);
        $this->assertSame(2, MorphVideo::find(1)->image->id);
        $this->assertNull(MorphArticle::find(2)->image);

        $relationship = $this->orm->getMapper(MorphArticle::class)->getRelationships()->get('image');
        $this->assertInstanceOf(MorphOne::class, $relationship);
        $this->assertTrue($relationship->isParent());
        $this->assertFalse($relationship->getOrphanRemoval());
    }

    public function testSingleChildReplacementOnlyDetachesTheChildOfTheParentType(): void
    {
        foreach ([true, false] as $loaded) {
            $this->connection->execute(
                "UPDATE morph_image SET imageable_type = 'article', imageable_id = 1 WHERE id = 1",
            );
            $article = MorphArticle::find(1);
            true === $loaded && $this->assertSame(1, $article->image->id);

            $image = new MorphImage();
            $image->url = 'new.png';
            $article->image = $image;
            $article->save();

            $this->assertSame(
                ['imageable_type' => 'article', 'imageable_id' => 1],
                $this->link('morph_image', $image->id, 'imageable'),
            );
            $this->assertNull($this->row('morph_image', 1)['imageable_id']);
            $this->assertSame(
                ['imageable_type' => 'video', 'imageable_id' => 1],
                $this->link('morph_image', 2, 'imageable'),
            );

            $this->connection->execute('DELETE FROM morph_image WHERE id = ' . (int)$image->id);
        }
    }

    public function testSingleChildOrphanRemovalDeletesOnlyTheChildOfTheParentType(): void
    {
        $article = MorphArticle::find(1);
        $article->ownedImage = null;
        $article->save();

        $this->assertNull($this->row('morph_image', 1));
        $this->assertNotNull($this->row('morph_image', 2));
    }

    public function testConditionThroughTheRelationshipJoinsOnTheDiscriminator(): void
    {
        $this->assertSame([1], $this->ids(MorphArticle::query()->where('comments.body', 'a1-first')->all()));
        $this->assertSame([], $this->ids(MorphArticle::query()->where('comments.body', 'v1-first')->all()));
        $this->assertSame([1], $this->ids(MorphVideo::query()->where('comments.body', 'v1-first')->all()));
        $this->assertSame([1], $this->ids(MorphVideo::query()->where('image.url', 'v1.png')->all()));
        $this->assertSame([], $this->ids(MorphVideo::query()->where('image.url', 'a1.png')->all()));
    }

    public function testIntegerZeroDiscriminatorRoundTrips(): void
    {
        $this->assertSame([1, 3], $this->ids(MorphArticle::find(1)->notes));
        $this->assertSame([2], $this->ids(MorphVideo::find(1)->notes));
        $this->assertSame([1], $this->ids(MorphArticle::query()->where('notes.body', 'article note')->all()));
        $this->assertSame([], $this->ids(MorphArticle::query()->where('notes.body', 'video note')->all()));

        // Writing
        $article = MorphArticle::find(2);
        $note = new MorphNote();
        $note->body = 'new';
        $article->notes[] = $note;
        $article->save();
        $this->assertSame(['owner_kind' => 0, 'owner_id' => 2], $this->noteLink($note->id));

        // Detachment and orphan removal both check the original discriminator of the child
        $article = MorphArticle::find(1);
        $notes = $article->notes;
        foreach ($notes as $key => $child) {
            if (1 === (int)$child->id) {
                unset($notes[$key]);
            }
        }
        $article->save();
        $this->assertSame(['owner_kind' => 0, 'owner_id' => null], $this->noteLink(1));

        $article = MorphArticle::find(1);
        $owned = $article->ownedNotes;
        $this->assertSame([3], $this->ids($owned));
        unset($owned[0]);
        $article->save();
        $this->assertNull($this->row('morph_note', 3));
        $this->assertSame(['owner_kind' => 1, 'owner_id' => 1], $this->noteLink(2));
    }

    public function testProgrammaticDeclaration(): void
    {
        $relationships = $this->orm->getMapper(MorphVideo::class)->getRelationships();
        $many = $relationships->morphMany(
            MorphComment::class,
            'programmaticComments',
            ['id' => 'commentable_id'],
            ['commentable_type' => 'video'],
            orphanRemoval: true,
        );
        $one = $relationships->morphOne(
            MorphImage::class,
            'programmaticImage',
            ['id' => 'imageable_id'],
            ['imageable_type' => 'video'],
        );

        $this->assertTrue($many->getOrphanRemoval());
        $this->assertSame($many, $relationships->get('programmaticComments'));
        $this->assertSame([3], $this->ids(MorphVideo::find(1)->programmaticComments));
        $this->assertSame(['imageable_type' => 'video'], $one->getDiscriminator());
        $this->assertSame(2, MorphVideo::find(1)->programmaticImage->id);
    }

    public function testRegularRelationshipsHaveNoDiscriminator(): void
    {
        $relationships = $this->orm->getMapper(MorphVideo::class)->getRelationships();

        $this->assertSame(
            [],
            $relationships->hasMany(MorphComment::class, 'plain', ['id' => 'commentable_id'])->getDiscriminator(),
        );
    }

    public function testPolymorphicRelationshipCannotBeInverted(): void
    {
        $relationships = $this->orm->getMapper(MorphComment::class)->getRelationships();

        foreach (['belongsTo', 'inverseOf'] as $method) {
            try {
                $relationships->{$method}(MorphArticle::class, 'article', 'comments');
                $this->fail(sprintf('%s() inverted a polymorphic relationship', $method));
            } catch (RelationException $exception) {
                $this->assertStringContainsString('cannot be inverted', $exception->getMessage());
            }
        }

        $this->assertFalse($relationships->exists('article'));
    }

    /**
     * @dataProvider invalidDeclarationProvider
     */
    public function testInvalidDeclarationIsRejected(array $columns, array $discriminator, string $message): void
    {
        $relationships = $this->orm->getMapper(MorphArticle::class)->getRelationships();

        foreach (['morphMany', 'morphOne'] as $method) {
            try {
                $relationships->{$method}(MorphComment::class, 'invalid', $columns, $discriminator);
                $this->fail(sprintf('%s() accepted an invalid declaration', $method));
            } catch (RelationException $exception) {
                $this->assertStringContainsString($message, $exception->getMessage());
            }
        }

        $this->assertFalse($relationships->exists('invalid'));
    }

    public static function invalidDeclarationProvider(): array
    {
        $columns = ['id' => 'commentable_id'];

        return [
            'missing columns' => [[], ['commentable_type' => 'article'], 'requires explicit columns'],
            'missing discriminator' => [$columns, [], 'requires a discriminator'],
            'unnamed discriminator column' => [$columns, ['article'], 'column names to string or integer values'],
            'null discriminator value' => [$columns, ['commentable_type' => null], 'string or integer values'],
            'boolean discriminator value' => [$columns, ['commentable_type' => false], 'string or integer values'],
            'float discriminator value' => [$columns, ['commentable_type' => 1.5], 'string or integer values'],
            'discriminator on a key column' => [$columns, ['commentable_id' => 1], 'already a key column'],
        ];
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

    private function row(string $table, int $id): ?array
    {
        return $this->connection->fetchOne(sprintf('SELECT * FROM %s WHERE id = ?', $table), [$id]);
    }

    private function noteLink(int $id): array
    {
        $row = $this->row('morph_note', $id);

        return [
            'owner_kind' => null === $row['owner_kind'] ? null : (int)$row['owner_kind'],
            'owner_id' => null === $row['owner_id'] ? null : (int)$row['owner_id'],
        ];
    }

    private function link(string $table, int $id, string $prefix): array
    {
        $row = $this->row($table, $id);
        $key = $row[$prefix . '_id'];

        return [
            $prefix . '_type' => $row[$prefix . '_type'],
            $prefix . '_id' => null === $key ? null : (int)$key,
        ];
    }
}

#[OrmAttribute\Table('morph_article', 'main')]
#[OrmAttribute\MorphMany(
    MorphComment::class,
    'comments',
    ['id' => 'commentable_id'],
    ['commentable_type' => 'article'],
)]
#[OrmAttribute\MorphMany(
    MorphComment::class,
    'ownedComments',
    ['id' => 'commentable_id'],
    ['commentable_type' => 'article'],
    orphanRemoval: true,
)]
#[OrmAttribute\MorphMany(MorphRequired::class, 'required', ['id' => 'owner_id'], ['owner_type' => 'article'])]
#[OrmAttribute\MorphMany(MorphNote::class, 'notes', ['id' => 'owner_id'], ['owner_kind' => 0])]
#[OrmAttribute\MorphMany(
    MorphNote::class,
    'ownedNotes',
    ['id' => 'owner_id'],
    ['owner_kind' => 0],
    orphanRemoval: true,
)]
#[OrmAttribute\MorphOne(MorphImage::class, 'image', ['id' => 'imageable_id'], ['imageable_type' => 'article'])]
#[OrmAttribute\MorphOne(
    MorphImage::class,
    'ownedImage',
    ['id' => 'imageable_id'],
    ['imageable_type' => 'article'],
    orphanRemoval: true,
)]
class MorphArticle extends MagicEntity
{
}

#[OrmAttribute\Table('morph_video', 'main')]
#[OrmAttribute\MorphMany(MorphComment::class, 'comments', ['id' => 'commentable_id'], ['commentable_type' => 'video'])]
#[OrmAttribute\MorphOne(MorphImage::class, 'image', ['id' => 'imageable_id'], ['imageable_type' => 'video'])]
#[OrmAttribute\MorphMany(MorphNote::class, 'notes', ['id' => 'owner_id'], ['owner_kind' => 1])]
class MorphVideo extends MagicEntity
{
}

#[OrmAttribute\Table('morph_comment', 'main')]
class MorphComment extends MagicEntity
{
}

#[OrmAttribute\Table('morph_image', 'main')]
class MorphImage extends MagicEntity
{
}

#[OrmAttribute\Table('morph_required', 'main')]
class MorphRequired extends MagicEntity
{
}

#[OrmAttribute\Table('morph_note', 'main')]
class MorphNote extends MagicEntity
{
}
