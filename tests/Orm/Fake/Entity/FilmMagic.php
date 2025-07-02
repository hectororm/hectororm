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

use Hector\Orm\Attributes as Orm;
use Hector\Orm\Entity\MagicEntity;

#[Orm\Table('film')]
#[Orm\HasOne(Language::class, 'language', ['language_id' => 'language_id'])]
#[Orm\HasOne(Language::class, 'original_language', ['original_language_id' => 'language_id'])]
#[Orm\BelongsToMany(Actor::class, 'actors')]
class FilmMagic extends MagicEntity
{
}