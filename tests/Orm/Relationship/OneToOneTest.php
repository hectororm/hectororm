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

namespace Hector\Orm\Tests\Relationship;

use Hector\Orm\Relationship\OneToOne;
use Hector\Orm\Tests\AbstractTestCase;
use Hector\Orm\Tests\Fake\Entity\Film;
use Hector\Orm\Tests\Fake\Entity\Language;

class OneToOneTest extends AbstractTestCase
{
    public function testLinkForeign()
    {
        $relationship = new OneToOne(
            'language',
            Film::class,
            Language::class,
            ['language_id' => 'language_id']
        );

        $film = new Film();
        $language = new Language();
        $language->name = 'Foo bar';

        $relationship->linkForeign($film, $language);

        $this->assertNotNull($language->language_id);
        $this->assertEquals($film->language_id, $language->language_id);
    }

    public function testReverse()
    {
        $relationship = new OneToOne(
            'language',
            Film::class,
            Language::class,
            ['language_id' => 'language_id']
        );
        $reverse = $relationship->reverse('film');

        $this->assertInstanceOf(OneToOne::class, $reverse);
        $this->assertEquals('film', $reverse->getName());
        $this->assertEquals($reverse->getSourceEntity(), $relationship->getTargetEntity());
        $this->assertEquals($reverse->getSourceColumns(), $relationship->getTargetColumns());
        $this->assertEquals($reverse->getTargetEntity(), $relationship->getSourceEntity());
        $this->assertEquals($reverse->getTargetColumns(), $relationship->getSourceColumns());
    }
}
