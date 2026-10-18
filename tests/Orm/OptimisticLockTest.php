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

namespace Hector\Orm\Tests;

use Hector\Connection\Connection;
use Hector\Connection\Log\Logger;
use Hector\Orm\Attributes as OrmAttribute;
use Hector\Orm\Entity\Entity;
use Hector\Orm\Entity\MagicEntity;
use Hector\Orm\Entity\ReflectionEntity;
use Hector\Orm\Exception\MapperException;
use Hector\Orm\Exception\OptimisticLockException;
use Hector\Orm\Exception\OrmException;
use Hector\Orm\Orm;
use Hector\Orm\Storage\EntityStorage;
use Hector\Query\Statement\Increment;
use Hector\Schema\Generator\Sqlite;
use Hector\Schema\SchemaContainer;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

class OptimisticLockTest extends TestCase
{
    private Connection $connection;
    private Logger $logger;
    private Orm $orm;
    private ?Orm $previousOrm;
    private array $previousReflections;

    protected function setUp(): void
    {
        $this->previousOrm = Orm::$instance;
        Orm::$instance = null;
        $property = new ReflectionProperty(ReflectionEntity::class, 'reflections');
        PHP_VERSION_ID < 80100 && $property->setAccessible(true);
        $this->previousReflections = $property->getValue();
        $property->setValue(null, []);

        $this->logger = new Logger();
        $this->connection = new Connection('sqlite::memory:', logger: $this->logger);
        $this->connection->execute(
            'CREATE TABLE versioned_article (id INTEGER PRIMARY KEY AUTOINCREMENT, title TEXT, version INTEGER)'
        );
        $this->connection->execute(
            'CREATE TABLE versioned_comment (id INTEGER PRIMARY KEY AUTOINCREMENT, article_id INTEGER, '
            . 'body TEXT, version INTEGER NOT NULL)'
        );
        $this->connection->execute(
            'CREATE TABLE versioned_line (tenant TEXT, ref INTEGER, label TEXT, version INTEGER NOT NULL, '
            . 'PRIMARY KEY (tenant, ref))'
        );
        $this->connection->execute(
            'CREATE TABLE versioned_note (id INTEGER PRIMARY KEY AUTOINCREMENT, body TEXT, revision INTEGER)'
        );
        $this->connection->execute('CREATE TABLE versioned_text (id INTEGER PRIMARY KEY, version TEXT)');
        $this->connection->execute('CREATE TABLE versioned_nopk (code TEXT, version INTEGER)');
        $this->connection->execute(
            "INSERT INTO versioned_article (id, title, version) VALUES (1, 'first', 1), (2, 'legacy', NULL)"
        );
        $this->connection->execute(
            "INSERT INTO versioned_comment (id, article_id, body, version) VALUES (10, 1, 'a', 1), (11, 1, 'b', 1)"
        );
        $this->connection->execute("INSERT INTO versioned_line VALUES ('acme', 1, 'line', 1)");
        $this->connection->execute("INSERT INTO versioned_note (id, body, revision) VALUES (1, 'note', 1)");
        $this->connection->execute("INSERT INTO versioned_text VALUES (1, '1')");
        $this->connection->execute("INSERT INTO versioned_nopk VALUES ('code', 1)");

        $schemas = new SchemaContainer([...(new Sqlite($this->connection))->generateSchemas('main')]);
        $this->orm = new Orm($this->connection, $schemas);
    }

    protected function tearDown(): void
    {
        Orm::$instance = $this->previousOrm;
        $property = new ReflectionProperty(ReflectionEntity::class, 'reflections');
        PHP_VERSION_ID < 80100 && $property->setAccessible(true);
        $property->setValue(null, $this->previousReflections);
    }

    private function row(string $table, int $id): ?array
    {
        return $this->connection->fetchOne(sprintf('SELECT * FROM %s WHERE id = ?', $table), [$id]);
    }

    private function bump(string $table, int $id): void
    {
        $this->connection->execute(
            sprintf("UPDATE %s SET version = version + 1, body = 'concurrent' WHERE id = ?", $table),
            [$id],
        );
    }

    private function original(Entity $entity): ?array
    {
        return ReflectionEntity::get($entity)->getHectorData($entity)->get('original');
    }

    private function assertConflict(callable $callback, Entity $entity, int $expectedVersion): void
    {
        try {
            $callback();
            $this->fail('An optimistic lock conflict is expected');
        } catch (OptimisticLockException $exception) {
            $this->assertSame($entity, $exception->getEntity());
            $this->assertSame($expectedVersion, $exception->getExpectedVersion());
        }
    }

    /////////////////////
    /// CONFIGURATION ///
    /////////////////////

    public function testVersionAttributeIsInheritedAndSerialized(): void
    {
        $reflection = ReflectionEntity::get(VersionedArticleChild::class);
        $this->assertSame('version', $reflection->getVersionColumn());
        $this->assertNull(ReflectionEntity::get(UnversionedArticle::class)->getVersionColumn());

        $unserialized = unserialize(serialize($reflection));
        $this->assertSame('version', $unserialized->getVersionColumn());
    }

    public function invalidConfigurationProvider(): array
    {
        return [
            'missing column' => [VersionedMissingColumn::class, 'does not exist'],
            'not an integer' => [VersionedTextColumn::class, 'must be an integer column'],
            'primary key' => [VersionedPrimaryColumn::class, 'cannot be part of the primary key'],
            'no primary key' => [VersionedWithoutPrimary::class, 'requires a primary key'],
        ];
    }

    /**
     * @dataProvider invalidConfigurationProvider
     */
    public function testInvalidVersionColumnIsRejected(string $class, string $message): void
    {
        $entity = $class::query()->get();

        try {
            $entity->expectVersion(1);
            $this->fail('An invalid version column must be rejected');
        } catch (OrmException $exception) {
            $this->assertStringContainsString($message, $exception->getMessage());
        }

        $this->expectException(OrmException::class);
        $this->expectExceptionMessage($message);
        $entity->save();
    }

    /////////////////
    /// INSERTION ///
    /////////////////

    public function testInsertInitializesVersion(): void
    {
        $article = new VersionedArticle();
        $article->title = 'new';
        $article->save();

        $this->assertSame(1, $article->version);
        $this->assertSame(1, (int)$this->row('versioned_article', $article->id)['version']);
        $this->assertSame(1, (int)$this->original($article)['version']);
        $this->assertFalse($article->isAltered());

        $article->title = 'updated';
        $article->save();
        $this->assertSame(2, $article->version);
    }

    public function testInsertInitializesVersionOfClassicEntity(): void
    {
        $comment = new VersionedComment();
        $comment->body = 'new';
        $comment->save();

        $this->assertSame(1, $comment->version);
        $this->assertSame(1, (int)$this->row('versioned_comment', $comment->id)['version']);
    }

    public function testInsertRejectsVersionSetByUser(): void
    {
        $comment = new VersionedComment();
        $comment->body = 'new';
        $comment->version = 5;

        try {
            $comment->save();
            $this->fail('A version set by the user must be rejected');
        } catch (MapperException $exception) {
            $this->assertStringContainsString('initialized by the ORM', $exception->getMessage());
        }

        $this->assertSame(2, (int)$this->connection->fetchColumn('SELECT COUNT(*) FROM versioned_comment')[0]);
    }

    //////////////
    /// UPDATE ///
    //////////////

    public function testSuccessfulUpdateAdvancesVersionAndTrackedState(): void
    {
        $article = VersionedArticle::find(1);
        $article->title = 'changed';
        $article->save();

        $this->assertSame(2, $article->version);
        $this->assertSame(['changed', 2], [
            $this->row('versioned_article', 1)['title'],
            (int)$this->row('versioned_article', 1)['version'],
        ]);
        $this->assertSame(2, (int)$this->original($article)['version']);
        $this->assertSame('changed', $this->original($article)['title']);
        $this->assertFalse($article->isAltered());
        $this->assertSame(EntityStorage::STATUS_NONE, $this->orm->getStatus($article));
    }

    public function testUpdateAtomicallyComparesAndIncrementsVersion(): void
    {
        $article = VersionedArticle::find(1);
        $article->title = 'changed';
        $article->save();

        $statements = array_map(fn($entry): string => $entry->getStatement(), $this->logger->getLogs());
        $updates = array_values(array_filter($statements, fn(string $sql): bool => str_starts_with($sql, 'UPDATE')));

        $this->assertCount(1, $updates);
        $this->assertMatchesRegularExpression(
            '/SET .*"?version"? = "?version"? \\+ :\w+ WHERE .*"?id"? = .* AND .*"?version"? = /',
            $updates[0],
        );
    }

    public function testSecondConflictingUpdateFails(): void
    {
        $first = VersionedArticle::find(1);
        $second = VersionedArticle::find(1);

        $first->title = 'first writer';
        $first->save();

        $second->title = 'second writer';
        $this->assertConflict(fn() => $second->save(), $second, 1);

        $this->assertSame('first writer', $this->row('versioned_article', 1)['title']);
        $this->assertSame(2, (int)$this->row('versioned_article', 1)['version']);
        $this->assertSame(1, $second->version);
        $this->assertSame(1, (int)$this->original($second)['version']);
        $this->assertTrue($second->isAltered('title'));
    }

    public function testChangeBetweenWriteAndRefreshIsLoaded(): void
    {
        // Simulate another writer between the UPDATE and the refresh of the entity
        $this->connection->execute(
            'CREATE TRIGGER versioned_comment_race AFTER UPDATE OF body ON versioned_comment '
            . "WHEN NEW.body = 'race' BEGIN UPDATE versioned_comment SET version = version + 1 WHERE id = NEW.id; END"
        );

        $comment = VersionedComment::find(10);
        $comment->body = 'race';
        $comment->save();

        // Like an explicit refresh, the entity reflects the latest row and has no pending change
        $this->assertSame(3, (int)$this->row('versioned_comment', 10)['version']);
        $this->assertSame(3, $comment->version);
        $this->assertSame(3, (int)$this->original($comment)['version']);
        $this->assertFalse($comment->isAltered());

        $comment->body = 'next';
        $comment->save();
        $this->assertSame(4, $comment->version);
    }

    public function testUpdateOfConcurrentlyDeletedRowFails(): void
    {
        $comment = VersionedComment::find(10);
        $this->connection->execute('DELETE FROM versioned_comment WHERE id = 10');
        $comment->body = 'changed';

        $this->assertConflict(fn() => $comment->save(), $comment, 1);
    }

    public function testCompositePrimaryKey(): void
    {
        $line = new VersionedLine();
        $line->tenant = 'acme';
        $line->ref = 2;
        $line->label = 'new';
        $line->save();
        $this->assertSame(1, $line->version);

        $first = VersionedLine::query()->whereEquals(['tenant' => 'acme', 'ref' => 1])->get();
        $second = VersionedLine::query()->whereEquals(['tenant' => 'acme', 'ref' => 1])->get();
        $first->label = 'first';
        $first->save();
        $this->assertSame(2, $first->version);

        $second->label = 'second';
        $this->assertConflict(fn() => $second->save(), $second, 1);

        $third = VersionedLine::query()->whereEquals(['tenant' => 'acme', 'ref' => 1])->get();
        $this->connection->execute("UPDATE versioned_line SET version = 3 WHERE tenant = 'acme' AND ref = 1");
        $this->assertConflict(fn() => $third->delete(), $third, 2);
        $first->refresh();

        $first->delete();
        $this->assertEquals(
            [['tenant' => 'acme', 'ref' => 2, 'label' => 'new', 'version' => 1]],
            $this->connection->fetchAll('SELECT * FROM versioned_line'),
        );
    }

    public function testNoOpSaveDoesNotAdvanceVersion(): void
    {
        $article = VersionedArticle::find(1);
        $article->save();

        $this->assertSame(1, $article->version);
        $this->assertSame(1, (int)$this->row('versioned_article', 1)['version']);
        $this->assertSame(EntityStorage::STATUS_NONE, $this->orm->getStatus($article));
    }

    public function testNoOpSaveDetectsConcurrentChange(): void
    {
        $comment = VersionedComment::find(10);
        $this->bump('versioned_comment', 10);

        $this->assertConflict(fn() => $comment->save(), $comment, 1);

        // The entity was refreshed: without pending change, saving it again is legitimate
        $this->assertSame(2, $comment->version);
        $this->assertSame('concurrent', $comment->body);
        $this->assertSame(2, (int)$this->original($comment)['version']);
        $comment->save();
        $this->assertSame(2, (int)$this->row('versioned_comment', 10)['version']);
    }

    public function testUnversionedEntityIsUnchanged(): void
    {
        $first = UnversionedArticle::find(1);
        $second = UnversionedArticle::find(1);
        $first->title = 'first';
        $first->save();
        $second->title = 'second';
        $second->save();

        $this->assertSame(['second', 1], [
            $this->row('versioned_article', 1)['title'],
            (int)$this->row('versioned_article', 1)['version'],
        ]);

        $second->version = 7;
        $second->save();
        $this->assertSame(7, (int)$this->row('versioned_article', 1)['version']);
    }

    ////////////////
    /// DELETION ///
    ////////////////

    public function testStaleDeletionFails(): void
    {
        $first = VersionedArticle::find(1);
        $second = VersionedArticle::find(1);
        $first->title = 'changed';
        $first->save();

        $this->assertConflict(fn() => $second->delete(), $second, 1);
        $this->assertNotNull($this->row('versioned_article', 1));

        $first->delete();
        $this->assertNull($this->row('versioned_article', 1));
        $this->assertNull($this->orm->getStatus($first));
    }

    //////////////////////////////////////
    /// MISSING DATA AND MANUAL WRITES ///
    //////////////////////////////////////

    public function testPartialHydrationRejectsWrites(): void
    {
        $article = VersionedArticle::query()->resetColumns()->column('id')->column('title')->get();
        $article->title = 'partial';

        try {
            $article->save();
            $this->fail('A write without loaded version must be rejected');
        } catch (MapperException $exception) {
            $this->assertStringContainsString('is not loaded', $exception->getMessage());
        }

        $this->expectException(MapperException::class);
        $this->expectExceptionMessage('is not loaded');
        $this->orm->getMapper($article)->deleteEntity($article);
    }

    public function testNullVersionRejectsWrites(): void
    {
        $article = VersionedArticle::find(2);
        $article->title = 'changed';

        $this->expectException(MapperException::class);
        $this->expectExceptionMessage('is not loaded');
        $article->save();
    }

    public function testManualVersionMutationOfClassicEntityIsRejected(): void
    {
        $comment = VersionedComment::find(10);
        $comment->version = 9;
        $comment->body = 'changed';

        try {
            $comment->save();
            $this->fail('A manual version must be rejected');
        } catch (MapperException $exception) {
            $this->assertStringContainsString('managed by the ORM', $exception->getMessage());
        }

        $this->assertSame(['a', 1], [
            $this->row('versioned_comment', 10)['body'],
            (int)$this->row('versioned_comment', 10)['version'],
        ]);
    }

    public function testManualVersionAssignmentOfMagicEntityIsRejected(): void
    {
        $article = VersionedArticle::find(1);

        $this->expectException(OrmException::class);
        $this->expectExceptionMessage('read-only');
        $article->version = 9;
    }

    ////////////////////////
    /// EXPECTED VERSION ///
    ////////////////////////

    public function testExpectedVersionFromPreviousRequestIsValidated(): void
    {
        // Request 1: the client reads the article and keeps its version
        $clientVersion = VersionedArticle::find(1)->version;

        // Meanwhile, another client updates the article
        $other = VersionedArticle::find(1);
        $other->title = 'other';
        $other->save();

        // Request 2: the article is loaded again, then the client version is checked
        $article = VersionedArticle::find(1);
        $this->assertConflict(fn() => $article->expectVersion($clientVersion), $article, 1);

        $article->expectVersion(2);
        $article->title = 'mine';
        $article->save();
        $this->assertSame(3, $article->version);
    }

    public function testExpectedVersionSurvivesRefresh(): void
    {
        $comment = VersionedComment::find(10);
        $comment->expectVersion(1);
        $this->bump('versioned_comment', 10);

        $comment->refresh();
        $this->assertSame(2, $comment->version);
        $comment->body = 'changed';

        $this->assertConflict(fn() => $comment->save(), $comment, 1);
        $this->assertSame('concurrent', $this->row('versioned_comment', 10)['body']);
    }

    public function testExpectedVersionIsCheckedOnNoOpSave(): void
    {
        $comment = VersionedComment::find(10);
        $comment->expectVersion(1);
        $this->bump('versioned_comment', 10);
        $comment->refresh();

        $this->assertConflict(fn() => $comment->save(), $comment, 1);
        // The expected version is kept until a successful write
        $this->assertConflict(fn() => $comment->save(), $comment, 1);
    }

    public function testExpectedVersionIsCheckedOnDeletion(): void
    {
        $comment = VersionedComment::find(10);
        $comment->expectVersion(1);
        $this->bump('versioned_comment', 10);
        $comment->refresh();

        $this->assertConflict(fn() => $comment->delete(), $comment, 1);
        $this->assertNotNull($this->row('versioned_comment', 10));
    }

    public function testExpectedVersionIsReleasedAfterSuccessfulWrite(): void
    {
        $comment = VersionedComment::find(10);
        $comment->expectVersion(1);
        $comment->body = 'first';
        $comment->save();
        $this->assertSame(2, $comment->version);

        $comment->body = 'second';
        $comment->save();
        $this->assertSame(3, $comment->version);
    }

    public function testExpectedVersionRequiresLoadedVersion(): void
    {
        $this->expectException(MapperException::class);
        $this->expectExceptionMessage('is not loaded');

        (new VersionedComment())->expectVersion(1);
    }

    public function testExpectedVersionRequiresVersionedEntity(): void
    {
        $this->expectException(OrmException::class);
        $this->expectExceptionMessage('is not versioned');

        UnversionedArticle::find(1)->expectVersion(1);
    }

    //////////////////////////////////
    /// TRANSACTIONS AND LIFECYCLE ///
    //////////////////////////////////

    public function testLifecycleRollbackRestoresVersionsAndAllowsRetry(): void
    {
        $article = VersionedArticle::find(1);
        $comments = $article->comments;
        $article->title = 'changed';
        $comments[1]->body = 'mine';
        $this->bump('versioned_comment', 11);

        $this->assertConflict(fn() => $article->save(cascade: true), $comments[1], 1);

        $this->assertSame(['first', 1], [
            $this->row('versioned_article', 1)['title'],
            (int)$this->row('versioned_article', 1)['version'],
        ]);
        $this->assertSame(1, $article->version);
        $this->assertSame(1, (int)$this->original($article)['version']);
        $this->assertTrue($article->isAltered('title'));

        $comments[1]->refresh();
        $comments[1]->body = 'mine';
        $article->save(cascade: true);

        $this->assertSame(2, $article->version);
        $this->assertSame(3, $comments[1]->version);
        $this->assertSame(2, (int)$this->row('versioned_article', 1)['version']);
        $this->assertSame('mine', $this->row('versioned_comment', 11)['body']);
    }

    public function testNoOpSaveConflictRollsBackLifecycleOperation(): void
    {
        $article = VersionedArticle::find(1);
        $comments = $article->comments;
        $comments[0]->body = 'mine';
        $this->connection->execute('UPDATE versioned_article SET version = 2 WHERE id = 1');

        $this->assertConflict(fn() => $article->save(cascade: true), $article, 1);

        $this->assertSame('a', $this->row('versioned_comment', 10)['body']);
        $this->assertSame(1, (int)$this->row('versioned_comment', 10)['version']);
        $this->assertSame(1, $article->version);
        $this->assertSame(1, $comments[0]->version);
        $this->assertSame('mine', $comments[0]->body);
    }

    public function testConflictIsNotRetriedByTransaction(): void
    {
        $comment = VersionedComment::find(10);
        $this->bump('versioned_comment', 10);
        $calls = 0;

        try {
            $this->orm->transaction(function () use ($comment, &$calls): void {
                $calls++;
                $comment->body = 'changed';
                $comment->save();
            }, attempts: 3);
            $this->fail('An optimistic lock conflict is expected');
        } catch (OptimisticLockException $exception) {
            $this->assertSame($comment, $exception->getEntity());
        }

        $this->assertSame(1, $calls);
        $this->assertFalse($this->connection->inTransaction());
    }

    public function testConflictIsNotWrappedByPersist(): void
    {
        $comment = VersionedComment::find(10);
        $this->bump('versioned_comment', 10);
        $comment->body = 'changed';
        $this->orm->save($comment);

        $this->assertConflict(fn() => $this->orm->persist(), $comment, 1);
    }

    public function testBulkBuilderUpdateIsNotVersionChecked(): void
    {
        $article = VersionedArticle::find(1);
        VersionedArticle::query()->whereEquals(['id' => 1])->update(['title' => 'bulk']);

        $this->assertSame(['bulk', 1], [
            $this->row('versioned_article', 1)['title'],
            (int)$this->row('versioned_article', 1)['version'],
        ]);

        // An explicit increment invalidates loaded entities
        VersionedArticle::query()->whereEquals(['id' => 1])->update(['title' => 'bulk', 'version' => new Increment()]);
        $this->assertSame(2, (int)$this->row('versioned_article', 1)['version']);

        $article->title = 'stale';
        $this->assertConflict(fn() => $article->save(), $article, 1);
    }
}

#[OrmAttribute\Table('versioned_article', 'main')]
#[OrmAttribute\Version('version')]
#[OrmAttribute\HasMany(VersionedComment::class, 'comments', ['id' => 'article_id'], orphanRemoval: false)]
class VersionedArticle extends MagicEntity
{
}

class VersionedArticleChild extends VersionedArticle
{
}

#[OrmAttribute\Table('versioned_article', 'main')]
class UnversionedArticle extends MagicEntity
{
}

#[OrmAttribute\Table('versioned_comment', 'main')]
#[OrmAttribute\Version('version')]
class VersionedComment extends Entity
{
    public ?int $id = null;
    public ?int $article_id = null;
    public ?string $body = null;
    public ?int $version = null;
}

#[OrmAttribute\Table('versioned_line', 'main')]
#[OrmAttribute\Version('version')]
class VersionedLine extends MagicEntity
{
}

#[OrmAttribute\Table('versioned_note', 'main')]
#[OrmAttribute\Version('unknown')]
class VersionedMissingColumn extends MagicEntity
{
}

#[OrmAttribute\Table('versioned_text', 'main')]
#[OrmAttribute\Version('version')]
class VersionedTextColumn extends MagicEntity
{
}

#[OrmAttribute\Table('versioned_note', 'main')]
#[OrmAttribute\Version('id')]
class VersionedPrimaryColumn extends MagicEntity
{
}

#[OrmAttribute\Table('versioned_nopk', 'main')]
#[OrmAttribute\Version('version')]
class VersionedWithoutPrimary extends MagicEntity
{
}
