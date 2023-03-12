<?php
namespace BeechIt\NewsTtnewsimport\Service\Util;

use Sys25\RnBase\Utility\Strings;

class LinkProcessor
{
    public function extractRelatedLinks($newsLinks)
    {
        $links = [];

        if (empty($newsLinks)) {
            return $links;
        }

        $newsLinks = str_replace(array('<link ', '</link>'), array('<LINK ', '</LINK>'), $newsLinks);

        if (str_contains($newsLinks, '</LINK>')) {

            $linkList = Strings::trimExplode('</LINK>', $newsLinks, true);
            foreach ($linkList as $singleLink) {
                if (strpos($singleLink, '<LINK') === false) {
                    continue;
                }
                $title = substr(strrchr($singleLink, '>'), 1);
                $uri = str_replace('>' . $title, '', substr(strrchr($singleLink, '<link '), 6));
                $links[] = [
                    'uri' => $uri,
                    'title' => $title,
                    'description' => '',
                ];
            }
        } else {
            $linkList = Strings::trimExplode("\n", $newsLinks, true);
            foreach ($linkList as $singleLink) {
                $singleLink = trim($singleLink);
                if ($title = parse_url($singleLink, PHP_URL_HOST) !== false ) {
                    $links[] = [
                        'uri' => $singleLink,
                        'title' => $title,
                        'description' => '',
                    ];
                }
            }
        }

        return $links;
    }
}