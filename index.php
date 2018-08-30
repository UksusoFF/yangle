<?php

require 'vendor/autoload.php';

use Doctrine\Common\Cache\FilesystemCache;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use Kevinrob\GuzzleCache\CacheMiddleware;
use Kevinrob\GuzzleCache\Storage\DoctrineCacheStorage;
use Kevinrob\GuzzleCache\Strategy\GreedyCacheStrategy;

$dotenv = new Dotenv\Dotenv(__DIR__);
$dotenv->load();

$stack = HandlerStack::create();
$stack->push(
    new CacheMiddleware(
        new GreedyCacheStrategy(
            new DoctrineCacheStorage(
                new FilesystemCache('cache/')
            ),
            (int)getenv('CACHE_LIFETIME')
        )
    ),
    'cache'
);

$client = new Client([
    'handler' => $stack,
]);

?>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Custom Yangle Search</title>
    <style type="text/css">
        body {
            font-family: arial, sans-serif;
            margin-top: 30px;
        }

        p {
            margin: 0;
            padding: 0;
        }

        .logo {
            font-size: 2em;
            font-weight: bolder;
            text-align: center;
        }

        .search {
            text-align: center;
            margin: 1em auto;
            max-width: 600px;
        }

        .search input {
            background-color: #fff;
            width: 80%;
            font-size: 1.4em;
            padding: 2px 10px;
            height: 2em;
            vertical-align: top;
            border: none;
            border-radius: 2px;
            box-shadow: 0 2px 2px 0 rgba(0, 0, 0, 0.26), 0 0 0 2px rgba(0, 0, 0, 0.08);
            transition: box-shadow 200ms cubic-bezier(0.4, 0.0, 0.2, 1);
        }

        .search input:hover {
            box-shadow: 0 2px 2px 0 rgba(0, 0, 0, 0.36), 0 0 0 2px rgba(0, 0, 0, 0.20);
        }

        .search input[type=submit] {
            margin-left: 3px;
            width: initial;
            cursor: pointer;
        }

        .results-content-container {
            width: 50%;
            float: left;
            padding: 0 15px;
            box-sizing: border-box;
        }

        @media (max-width: 700px) {
            .results-content-container {
                width: 100%;
                float: none;
            }
        }

        .results-content-container .copyright img {
            line-height: 20px;
            font-size: 20px;
        }

        .results-content-container .copyright img {
            margin-right: 5px;
            max-height: 20px;
            vertical-align: middle;
        }

        .item {
            margin: 15px 0;
        }

        .item a {
            text-decoration: none;
            color: #609;
            font-size: 1em;
        }

        .item a:hover {
            text-decoration: underline;
        }

        .item a:visited {
            color: #1a0dab;
        }

        .item .link {
            color: #006621;
            margin-bottom: 5px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .item .desc {
            color: #444;
            font-size: 0.8em;
        }
    </style>
</head>
<body>

<?php $search = isset($_GET['q']) ? $_GET['q'] : null; ?>

<div class="logo">
    <span style="color: red;">Y</span>
    <span style="color: black;">a</span>
    <span style="color: black;">n</span>
    <span style="color: #4285F4">g</span>
    <span style="color: #34A853">l</span>
    <span style="color: #EA4335">e</span>
</div>

<div class="search">
    <form>
        <input type="text" name="q" autofocus value="<?php echo htmlentities($search); ?>">
        <input class="button" type="submit" value="&#128270;">
    </form>
</div>

<?php if (empty($search)): ?>

    //

<?php else: ?>

    <?php

    $yandex = simplexml_load_string((string)$client->get('https://yandex.ru/search/xml', [
        'query' => [
            'key' => getenv('YANDEX_KEY'),
            'user' => getenv('YANDEX_UID'),
            'filter' => 'none',
            'query' => $search,
        ],
    ])->getBody());

    $items['Y'] = [];

    $total['Y'] =
        '<span class="copyright">' .
        '<a href="https://yandex.ru" target="_blank"><img src="images/yandex_logo.png"></a>' .
        (
        !isset($yandex->response->results) ?
            strip_tags($yandex->response->error->asXML()) :
            strip_tags($yandex->response->results->grouping->{'found-docs-human'}->asXML())
        ) .
        '</span>';

    foreach ($yandex->response->results->grouping->group ?? [] as $groupItem) {
        $items['Y'][] = [
            'title' => strip_tags($groupItem->doc->title->asXML()),
            'description' => strip_tags($groupItem->doc->passages->asXML()),
            'url' => strip_tags($groupItem->doc->url->asXML()),
        ];
    }

    $google = json_decode((string)$client->get('https://www.googleapis.com/customsearch/v1', [
        'query' => [
            'key' => getenv('GOOGLE_KEY'),
            'cx' => getenv('GOOGLE_SID'),
            'q' => $search,
        ],
    ])->getBody(), true);

    $items['G'] = [];

    $total['G'] =
        '<span class="copyright">' .
        '<a href="https://google.ru" target="_blank"><img src="images/google_logo.png"></a>' .
        (
        $google['searchInformation']['totalResults'] === '0' ?
            'Ничего не нашлось :(' :
            "нашёл {$google['searchInformation']['formattedTotalResults']} ответов"
        ) .
        '</span>';

    foreach ($google['items'] ?? [] as $item) {
        $items['G'][] = [
            'title' => htmlentities($item['title']),
            'description' => htmlentities($item['snippet']),
            'url' => $item['link'],
        ];
    }

    ?>

    <?php foreach ($items as $system => $results): ?>
        <div class="results-content-container">
            <?php echo $total[$system]; ?>
            <?php foreach ($results as $result): ?>
                <div class="item">
                    <p class="title">
                        <a target="_blank" href="<?php echo $result['url']; ?>">
                            <?php echo $result['title']; ?>
                        </a>
                    </p>
                    <p class="link"><?php echo $result['url']; ?></p>
                    <p class="desc"><?php echo $result['description']; ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endforeach; ?>

<?php endif; ?>

</body>
</html>
