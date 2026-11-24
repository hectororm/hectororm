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
use Hector\Orm\Relationship\MorphToMany;
use Hector\Schema\Generator\Sqlite;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

class MorphToManyTest extends TestCase
{
    private const PIVOT = 'morphm_taggable';
    private const FROM = ['id' => 'taggable_id'];
    private const TO = ['tag_id' => 'id'];

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
            'CREATE TABLE morphm_article (id INTEGER PRIMARY KEY AUTOINCREMENT, title TEXT)',
            'CREATE TABLE morphm_video (id INTEGER PRIMARY KEY AUTOINCREMENT, title TEXT)',
            'CREATE TABLE morphm_tag (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT)',
            'CREATE TABLE morphm_taggable (tag_id INTEGER NOT NULL, taggable_type TEXT NOT NULL, '
                . 'taggable_id INTEGER NOT NULL, weight INTEGER NOT NULL DEFAULT 0, '
                . 'PRIMARY KEY (tag_id, taggable_type, taggable_id))',
        ] as $sql) {
            $this->connection->execute($sql);
        }

        // Articles and videos share identifiers: only the discriminator of the pivot tells their tags apart.
        $this->connection->execute("INSERT INTO morphm_article VALUES (1, 'first article'), (2, 'second article')");
        $this->connection->execute("INSERT INTO morphm_video VALUES (1, 'first video'), (2, 'second video')");
        $this->connection->execute(
            "INSERT INTO morphm_tag VALUES (1, 'php'), (2, 'orm'), (3, 'video-only'), (4, 'unused')",
        );
        $this->connection->execute(
            "INSERT INTO morphm_taggable VALUES (1, 'article', 1, 10), (2, 'article', 1, 20), "
            . "(1, 'video', 1, 30), (3, 'video', 1, 40), (2, 'article', 2, 50)",
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

    public function testLazyLoadingOnlyFollowsPivotRowsOfTheDeclaredType(): void
    {
        $this->assertSame([1, 2], $this->ids(MArticle::find(1)->tags));
        $this->assertSame([2], $this->ids(MArticle::find(2)->tags));
        $this->assertSame([1, 3], $this->ids(MVideo::find(1)->tags));
        $this->assertSame([], $this->ids(MVideo::find(2)->tags));
    }

    public function testOppositeSideOnlyFollowsPivotRowsOfTheDeclaredType(): void
    {
        $this->assertSame([1], $this->ids(MTag::find(1)->articles));
        $this->assertSame([1, 2], $this->ids(MTag::find(2)->articles));
        $this->assertSame([], $this->ids(MTag::find(3)->articles));
        $this->assertSame([1], $this->ids(MTag::find(1)->videos));
        $this->assertSame([1], $this->ids(MTag::find(3)->videos));
        $this->assertSame([], $this->ids(MTag::find(2)->videos));
    }

    public function testEagerLoadingOnlyFollowsPivotRowsOfTheDeclaredType(): void
    {
        $articles = MArticle::query()->orderBy('id')->with(['tags'])->all();
        $this->assertSame([1, 2], $this->ids($articles[0]->tags));
        $this->assertSame([2], $this->ids($articles[1]->tags));

        $tags = MTag::query()->orderBy('id')->with(['articles', 'videos'])->all();
        $this->assertSame([1], $this->ids($tags[0]->articles));
        $this->assertSame([1], $this->ids($tags[0]->videos));
        $this->assertSame([1, 2], $this->ids($tags[1]->articles));
        $this->assertSame([], $this->ids($tags[1]->videos));
        $this->assertSame([], $this->ids($tags[3]->articles));
    }

    public function testAttachmentWritesTheDiscriminatorInThePivotRow(): void
    {
        $article = MArticle::find(2);
        $article->tags[] = MTag::find(3);
        $article->save();
        $article->save();

        $this->assertSame(
            ['1/article/1', '1/video/1', '2/article/1', '2/article/2', '3/article/2', '3/video/1'],
            $this->pivotRows(),
        );
        $this->assertSame([2, 3], $this->ids(MArticle::find(2)->tags));
    }

    public function testAttachmentIsNotMistakenForALinkOfAnotherType(): void
    {
        // The pivot already links tag 2 to the article sharing the identifier of this video.
        $video = MVideo::find(2);
        $video->tags[] = MTag::find(2);
        $video->save();

        $this->assertContains('2/video/2', $this->pivotRows());
        $this->assertContains('2/article/2', $this->pivotRows());
        $this->assertSame([2], $this->ids(MVideo::find(2)->tags));
    }

    public function testNewEntityIsLinkedWithTheDiscriminator(): void
    {
        $video = new MVideo();
        $video->title = 'new video';
        $video->tags = new Collection([MTag::find(4)]);
        $video->save();

        $this->assertContains(sprintf('4/video/%d', $video->id), $this->pivotRows());
    }

    public function testDetachmentOnlyDeletesThePivotRowOfTheDeclaredType(): void
    {
        $article = MArticle::find(1);
        $tags = $article->tags;
        foreach ($tags as $key => $tag) {
            if (1 === (int)$tag->id) {
                unset($tags[$key]);
            }
        }
        $article->save();

        $this->assertSame(['1/video/1', '2/article/1', '2/article/2', '3/video/1'], $this->pivotRows());
        $this->assertSame([1, 3], $this->ids(MVideo::find(1)->tags));
    }

    public function testAdditionalPivotDataIsOnlyUpdatedOnTheRowOfTheDeclaredType(): void
    {
        $article = MArticle::find(1);
        foreach ($article->tags as $tag) {
            if (1 === (int)$tag->id) {
                $tag->getPivot()->setData(['weight' => 99]);
            }
        }
        $article->save();

        $this->assertSame(99, $this->weight(1, 'article', 1));
        $this->assertSame(30, $this->weight(1, 'video', 1));
        $this->assertSame(20, $this->weight(2, 'article', 1));
    }

    public function testAdditionalPivotDataCannotChangeTheDiscriminatorOfAnExistingLink(): void
    {
        // No pivot row links tag 2 to video 2: a changed discriminator would hand the link over to it.
        $article = MArticle::find(2);
        $tag = $article->tags[0];
        $this->assertSame(2, (int)$tag->id);
        $tag->getPivot()->setData(['taggable_type' => 'video', 'weight' => 77]);
        $article->save();

        $this->assertSame(
            ['1/article/1', '1/video/1', '2/article/1', '2/article/2', '3/video/1'],
            $this->pivotRows(),
        );
        $this->assertSame(77, $this->weight(2, 'article', 2));
        $this->assertSame([2], $this->ids(MArticle::find(2)->tags));
        $this->assertSame([], $this->ids(MVideo::find(2)->tags));
    }

    public function testAdditionalPivotDataCannotChangeTheDiscriminatorOfANewLink(): void
    {
        // The tag comes from a video, carrying the pivot data of that link.
        $tag = null;
        foreach (MVideo::find(1)->tags as $candidate) {
            if (3 === (int)$candidate->id) {
                $tag = $candidate;
            }
        }
        $tag->getPivot()->setData(['taggable_type' => 'video', 'weight' => 5]);

        $article = MArticle::find(2);
        $article->tags[] = $tag;
        $article->save();

        $this->assertSame(
            ['1/article/1', '1/video/1', '2/article/1', '2/article/2', '3/article/2', '3/video/1'],
            $this->pivotRows(),
        );
        $this->assertSame(5, $this->weight(3, 'article', 2));
        $this->assertSame(40, $this->weight(3, 'video', 1));
        $this->assertSame([], $this->ids(MVideo::find(2)->tags));
    }

    public function testConditionThroughTheRelationshipJoinsThePivotOnTheDiscriminator(): void
    {
        $this->assertSame([1], $this->ids(MArticle::query()->where('tags.name', 'php')->all()));
        $this->assertSame([], $this->ids(MArticle::query()->where('tags.name', 'video-only')->all()));
        $this->assertSame([1], $this->ids(MVideo::query()->where('tags.name', 'video-only')->all()));
        $this->assertSame([1, 2], $this->ids(MTag::query()->where('articles.title', 'first article')->all()));
        $this->assertSame([1, 3], $this->ids(MTag::query()->where('videos.title', 'first video')->all()));
    }

    public function testInversionKeepsThePivotDiscriminator(): void
    {
        $inverse = $this->orm->getMapper(MTag::class)->getRelationships()->get('videos');

        $this->assertInstanceOf(MorphToMany::class, $inverse);
        $this->assertSame(MVideo::class, $inverse->getTargetEntity());
        $this->assertSame(self::PIVOT, $inverse->getPivotTable());
        $this->assertSame(['taggable_type' => 'video'], $inverse->getPivotDiscriminator());
        $this->assertSame(['tag_id'], $inverse->getPivotTargetColumns());
        $this->assertSame(['taggable_id'], $inverse->getPivotSourceColumns());
    }

    public function testProgrammaticDeclaration(): void
    {
        $relationships = $this->orm->getMapper(MVideo::class)->getRelationships();
        $relationship = $relationships->morphToMany(
            MTag::class,
            'labels',
            self::PIVOT,
            self::FROM,
            self::TO,
            ['taggable_type' => 'video'],
        );

        $this->assertSame($relationship, $relationships->get('labels'));
        $this->assertSame([1, 3], $this->ids(MVideo::find(1)->labels));
    }

    public function testRegularManyToManyHasNoPivotDiscriminator(): void
    {
        $relationships = $this->orm->getMapper(MVideo::class)->getRelationships();
        $relationship = $relationships->belongsToMany(MTag::class, 'anyTags', self::PIVOT, self::FROM, self::TO);

        $this->assertSame([], $relationship->getPivotDiscriminator());
        $this->assertSame([1, 2, 3], array_values(array_unique($this->ids(MVideo::find(1)->anyTags))));
    }

    /**
     * @dataProvider invalidDeclarationProvider
     */
    public function testInvalidDeclarationIsRejected(
        array $columnsFrom,
        array $columnsTo,
        array $discriminator,
        string $message,
    ): void {
        $relationships = $this->orm->getMapper(MArticle::class)->getRelationships();

        try {
            $relationships->morphToMany(MTag::class, 'invalid', self::PIVOT, $columnsFrom, $columnsTo, $discriminator);
            $this->fail('An invalid declaration was accepted');
        } catch (RelationException $exception) {
            $this->assertStringContainsString($message, $exception->getMessage());
        }

        $this->assertFalse($relationships->exists('invalid'));
    }

    public static function invalidDeclarationProvider(): array
    {
        $type = ['taggable_type' => 'article'];

        return [
            'missing source columns' => [[], self::TO, $type, 'requires explicit columns'],
            'missing target columns' => [self::FROM, [], $type, 'requires explicit columns'],
            'missing discriminator' => [self::FROM, self::TO, [], 'requires a discriminator'],
            'unnamed discriminator column' => [self::FROM, self::TO, ['article'], 'string or integer values'],
            'null discriminator value' => [self::FROM, self::TO, ['taggable_type' => null], 'string or integer'],
            'boolean discriminator value' => [self::FROM, self::TO, ['taggable_type' => false], 'string or integer'],
            'float discriminator value' => [self::FROM, self::TO, ['taggable_type' => 1.5], 'string or integer'],
            'discriminator on a source key' => [self::FROM, self::TO, ['taggable_id' => 1], 'already a key column'],
            'discriminator on a target key' => [self::FROM, self::TO, ['tag_id' => 1], 'already a key column'],
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

    private function pivotRows(): array
    {
        $rows = [];
        foreach ($this->connection->fetchAll('SELECT * FROM morphm_taggable') as $row) {
            $rows[] = sprintf('%d/%s/%d', $row['tag_id'], $row['taggable_type'], $row['taggable_id']);
        }

        sort($rows);

        return $rows;
    }

    private function weight(int $tag, string $type, int $id): int
    {
        $row = $this->connection->fetchOne(
            'SELECT weight FROM morphm_taggable WHERE tag_id = ? AND taggable_type = ? AND taggable_id = ?',
            [$tag, $type, $id],
        );

        return (int)$row['weight'];
    }
}

#[OrmAttribute\Table('morphm_article', 'main')]
#[OrmAttribute\MorphToMany(
    MTag::class,
    'tags',
    'morphm_taggable',
    ['id' => 'taggable_id'],
    ['tag_id' => 'id'],
    ['taggable_type' => 'article'],
)]
class MArticle extends MagicEntity
{
}

#[OrmAttribute\Table('morphm_video', 'main')]
#[OrmAttribute\MorphToMany(
    MTag::class,
    'tags',
    'morphm_taggable',
    ['id' => 'taggable_id'],
    ['tag_id' => 'id'],
    ['taggable_type' => 'video'],
)]
class MVideo extends MagicEntity
{
}

#[OrmAttribute\Table('morphm_tag', 'main')]
#[OrmAttribute\MorphToMany(
    MArticle::class,
    'articles',
    'morphm_taggable',
    ['id' => 'tag_id'],
    ['taggable_id' => 'id'],
    ['taggable_type' => 'article'],
)]
#[OrmAttribute\InverseOf(MVideo::class, 'videos', 'tags')]
class MTag extends MagicEntity
{
}
