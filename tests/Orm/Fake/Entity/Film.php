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

use DateTime;
use Hector\Orm\Attributes\BelongsToMany;
use Hector\Orm\Attributes\HasOne;
use Hector\Orm\Entity\Entity;

#[HasOne(Language::class, 'language', ['language_id' => 'language_id'])]
#[HasOne(Language::class, 'original_language', ['original_language_id' => 'language_id'])]
#[BelongsToMany(Actor::class, 'actors')]
class Film extends Entity
{
    public ?int $film_id = null;
    public ?string $title = null;
    public ?string $description = null;
    public ?int $release_year = null;
    public ?int $language_id = null;
    public ?int $original_language_id = null;
    public ?int $rental_duration = null;
    public ?float $rental_rate = null;
    public ?int $length = null;
    public ?float $replacement_cost = null;
    public ?string $rating = null;
    public ?array $special_features = null;
    public ?DateTime $last_update = null;

    public function getActors(): \Hector\Orm\Collection\Collection
    {
        return $this->getRelated()->get('actors');
    }
}