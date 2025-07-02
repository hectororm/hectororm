<?php
/*
 * This file is part of Hector ORM.
 *
 * @license   https://opensource.org/licenses/MIT MIT License
 * @copyright 2021 Ronan GIRON
 * @author    Ronan GIRON <https://github.com/ElGigi>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code, to the root.
 */

namespace Hector\Orm\Tests\Fake\Entity;

use DateTimeImmutable;
use Hector\Orm\Attributes as Orm;
use Hector\Orm\Entity\MagicEntity;

/**
 * @property int $language_id
 * @property string $name
 * @property DateTimeImmutable $last_update
 */
#[Orm\Collection(LanguageCollection::class)]
#[Orm\HasMany(Film::class, 'films', ['language_id' => 'language_id'])]
class Language extends MagicEntity
{
}