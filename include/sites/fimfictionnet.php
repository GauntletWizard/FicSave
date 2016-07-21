<?php
use \Masterminds\HTML5;
function getFimfictionNetChapter($url, $chapterNumber) {
    $response = cURL($url . "/" . $chapterNumber);
    $html = new HTML5();
    $html = $html->loadHTML($response);

    $chapter = new Chapter;
    $chapter->number = $chapterNumber;

    $numChapters = qp($html, '#page_list')->find('ul')->get();
    $numChapters = $numChapters == 0 ? 1 : $numChapters;
    $chapterTitleContainer = qp($html, '#chapter_title');
    if ($chapterTitleContainer != NULL) {
        $chapterTitle = qp($chapterTitleContainer)->text();
        $chapterTitle = str_replace($chapterNumber.". ", "", $chapterTitle);
        $chapter->title = $chapterTitle;
    } else {
        $title = qp($html, '#profile_top')->find('b')->first()->text();
        if (empty($title)) {
            $chapter->title = 'Chapter ' . $chapterNumber;
        } else {
            $chapter->title = $title;
        }
    }

    $chapter->content = stripAttributes(qp($html, '.chapter_content')->innerHTML());

    return $chapter;
}

function getFimfictionNetInfo($url) {
    $urlParts = parse_url($url);
    $pathParts = explode('/', $urlParts['path']);
    if (isset($pathParts[2])) {
        $storyId = $pathParts[2];
        if (is_numeric($storyId)) {
            $response = cURL($url);
            //            throw new FicSaveException(htmlspecialchars($url));
            //  For whatever reason, it tries to load external entites. Stop that.
            $internalErrors = libxml_use_internal_errors(true);
            $html = new HTML5();
            $html = $html->loadHTML($response);

            $story = new Story;
            $story->id = $storyId;
            $urlParts = parse_url($url);
            $story->url = "{$urlParts['scheme']}://{$urlParts['host']}/story/{$storyId}";

            $title = qp($html, '.story_name')->first()->text();
            if (empty($title)) {
                throw new FicSaveException("Could not retrieve title for story at $url.");
            } else {
                $story->title = $title;
            }

            $author = qp($html, '.author')->find('a')->first()->text();
            if (empty($author)) {
                throw new FicSaveException("Could not retrieve author for story at $url.");
            } else {
                $story->author = $author;
            }

            $description = qp($html, '.description')->find('p')->first()->text();
            if ($description == NULL) {
                throw new FicSaveException("Could not retrieve description for story at $url.");
            } else {
                $story->description = stripAttributes(preg_replace('/<a(.*?)>(.*?)<\/a>/', '\2', trim(qp($description)->html() . qp($description)->next()->html())));
            }

            $numChapters = qp($html, '.chapter_container:not(.chapter_expander)')->count();
            $story->chapters = $numChapters == 0 ? 1 : $numChapters;

            $coverImageUrl = qp($html, 'a .story_image')->first()->attr('src');
            if ($coverImageUrl != NULL) {
                $coverImageUrlParts = parse_url($coverImageUrl);
                if (!isset($coverImageUrlParts['scheme']) && substr($coverImageUrl, 0, 2) == '//') {
                    $coverImageUrl = $urlParts['scheme'] . ":" . $coverImageUrl;
                }
                $coverImageUrl = str_replace('/medium/', '/large/', $coverImageUrl);
                $story->coverImageUrl = $coverImageUrl;
            }

            return $story;
        } else {
            throw new FicSaveException("URL has an invalid story ID: $storyId.");
        }
    } else {
        throw new FicSaveException("URL is missing story ID.");
    }
}
?>
