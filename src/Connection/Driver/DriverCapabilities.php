<?php
/*
 * This file is part of Hector ORM.
 *
 * @license   https://opensource.org/licenses/MIT MIT License
 * @copyright 2025 Ronan GIRON
 * @author    Ronan GIRON <https://github.com/ElGigi>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code, to the root.
 */

declare(strict_types=1);

namespace Hector\Connection\Driver;

/**
 * Capabilities of a database engine, for a given version.
 *
 * This interface describes the engines supported by Hector and grows with them:
 * methods can be added in minor releases. Third-party implementations must
 * follow these additions, which are listed in the package changelog.
 */
interface DriverCapabilities
{
    /**
     * Support of lock?
     *
     * @return bool
     */
    public function hasLock(): bool;

    /**
     * Support of lock and skip locked?
     *
     * @return bool
     */
    public function hasLockAndSkip(): bool;

    /**
     * Support of window functions?
     *
     * @return bool
     */
    public function hasWindowFunctions(): bool;

    /**
     * Support of JSON?
     *
     * @return bool
     */

    public function hasJson(): bool;

    /**
     * Has strict mode?
     *
     * @return bool
     */
    public function hasStrictMode(): bool;

    /**
     * Support of RENAME COLUMN syntax?
     *
     * When false, the compiler must use an alternative syntax
     * (e.g., CHANGE COLUMN on MySQL < 8.0).
     *
     * @return bool
     */
    public function hasRenameColumn(): bool;

    /**
     * Support of transactional DDL?
     *
     * When true, DDL statements (CREATE/ALTER/DROP TABLE) participate in the
     * surrounding transaction and are rolled back with it. When false, the
     * database issues an implicit COMMIT on DDL (e.g. MySQL/MariaDB), so a
     * failed migration cannot be fully rolled back.
     *
     * @return bool
     */
    public function hasTransactionalDdl(): bool;

    /**
     * Does the server enforce CHECK constraints rather than merely parse them?
     *
     * Describes engine support, not session settings that may disable enforcement.
     * Does not imply that a schema plan dialect exists for this engine.
     * Offline callers must supply capabilities for the intended engine and version.
     *
     * @return bool
     */
    public function hasCheckConstraints(): bool;

    /**
     * Does the server support the ALTER TABLE ... DROP CHECK name syntax?
     *
     * False does not imply support for DROP CONSTRAINT: SQLite needs a rebuild.
     * Check hasCheckConstraints() before selecting a constraint operation.
     *
     * @return bool
     */
    public function hasDropCheck(): bool;

    /**
     * Support of a native, atomic upsert (insert or update on conflict)?
     *
     * True for ON DUPLICATE KEY UPDATE (MySQL/MariaDB) and for
     * ON CONFLICT ... DO UPDATE (SQLite 3.24.0+, PostgreSQL 9.5+).
     *
     * @return bool
     */
    public function hasUpsert(): bool;

    /**
     * Support of a row alias on INSERT ... VALUES (...) AS alias?
     *
     * When true, ON DUPLICATE KEY UPDATE must reference the inserted row through
     * this alias (MySQL 8.0.19+), the VALUES() function being deprecated there.
     * When false, an engine using ON DUPLICATE KEY UPDATE relies on VALUES().
     *
     * @return bool
     */
    public function hasInsertRowAlias(): bool;
}
