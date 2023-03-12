<?php

namespace BeechIt\NewsTtnewsimport\Tests\Unit\Service\Util;

use BeechIt\NewsTtnewsimport\Service\Util\LinkProcessor;
use Sys25\RnBase\Testing\BaseTestCase;

/***************************************************************
*  Copyright notice
*
*  (c) 2012-2021 Rene Nitzsche (rene@system25.de)
*  All rights reserved
*
*  This script is part of the TYPO3 project. The TYPO3 project is
*  free software; you can redistribute it and/or modify
*  it under the terms of the GNU General Public License as published by
*  the Free Software Foundation; either version 2 of the License, or
*  (at your option) any later version.
*
*  The GNU General Public License can be found at
*  http://www.gnu.org/copyleft/gpl.html.
*
*  This script is distributed in the hope that it will be useful,
*  but WITHOUT ANY WARRANTY; without even the implied warranty of
*  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*  GNU General Public License for more details.
*
*  This copyright notice MUST APPEAR in all copies of the script!
***************************************************************/

/**
 * @group unit
 */
class LinkProcessorTest extends BaseTestCase
{
    /**
     * @dataProvider getLinkSet
     */
    public function testExtractRelatedLinks($links, $expected)
    {
        $linkProc = new LinkProcessor();
        $this->assertEquals($expected, $linkProc->extractRelatedLinks($links));
    }

    public function getLinkSet()
    {
        return [
            [
'http://www.hsv.de/saison/hsv-ii/stadioneintrittspreise/
http://www.hsv.de/saison/hsv-ii/impressionen/', [
                ['uri' => 'http://www.hsv.de/saison/hsv-ii/stadioneintrittspreise/', 'title' => 'www.hsv.de', 'description' => ''],
                ['uri' => 'http://www.hsv.de/saison/hsv-ii/impressionen/', 'title' => 'www.hsv.de', 'description' => ''],
            ]],
            ['http://www.fcoberneuland-bremen.de', [
                ['uri' => 'http://www.fcoberneuland-bremen.de', 'title' => 'www.fcoberneuland-bremen.de', 'description' => ''],
            ]],
            ['<LINK http://www.chempixx.de>www.chempixx.de</LINK>', [
                ['uri' => 'http://www.chempixx.de', 'title' => 'www.chempixx.de', 'description' => ''],
            ]],
            ['', []],
        ];
    }

}
