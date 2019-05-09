<?php
  ini_set('default_socket_timeout', 5);

  define('CACHE_DIRECTORY', __DIR__ . '/cache');
  define('CACHE_MAP', CACHE_DIRECTORY . '/cache.php');

  function slugify($string, $replace = array(), $delimiter = '-') {
    if (!extension_loaded('iconv')) {
      throw new Exception('iconv module not loaded');
    }

    // Save the old locale and set the new locale to UTF-8
    $oldLocale = setlocale(LC_ALL, '0');
    setlocale(LC_ALL, 'en_US.UTF-8');
    $clean = iconv('UTF-8', 'ASCII//TRANSLIT', $string);

    if (!empty($replace)) {
      $clean = str_replace((array) $replace, ' ', $clean);
    }

    $clean = preg_replace("/[^a-zA-Z0-9\/_|+ -]/", '', $clean);
    $clean = strtolower($clean);
    $clean = preg_replace("/[\/_|+ -]+/", $delimiter, $clean);
    $clean = trim($clean, $delimiter);

    // Revert back to the old locale
    setlocale(LC_ALL, $oldLocale);
    return $clean;
  }

  function url_to_filename($url) {
    $url = parse_url($url);
    return slugify($url['host'] . $url['path']);
  }

  function setup_cache() {
    if (!file_exists(CACHE_DIRECTORY)) {
      mkdir(CACHE_DIRECTORY);
    }

    if (!file_exists(CACHE_MAP)) {
      mkdir(CACHE_MAP);
    }
  }

  function request($url, $hours = 1) {
    $current_time = time();
    $expire_time = $hours * 60 * 60;
    $cache_file = CACHE_DIRECTORY . '/' . url_to_filename($url);

    if (file_exists($cache_file) && ($current_time - $expire_time < filemtime($cache_file))) {
      return file_get_contents($cache_file);
    }

    $response = file_get_contents($url);

    if ($response) {
      file_put_contents($cache_file, $response);
    } else {
      return file_get_contents($cache_file);
    }

    return $response;
  }

  setup_cache();

  $dn_json_response = request('http://api.pnd.gs/v1/sources/designerNews/popular');
  $hn_json_response = request('http://api.pnd.gs/v1/sources/hackerNews/popular');

  $dn_feed = json_decode($dn_json_response);
  $hn_feed = json_decode($hn_json_response);
?>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta http-equiv="X-UA-Compatible" content="ie=edge">
  <title>News</title>

  <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
  <link rel="icon" type="image/png" sizes="96x96" href="/favicon-96x96.png">
  <link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">

  <style>
    <?= file_get_contents(__DIR__ . '/main.css'); ?>
  </style>
</head>
<body>
  <div class="feeds">
    <div class="feed feed--dn">
      <header class="feed-header feed-header--dn">Designer News</header>
      <?php foreach($dn_feed as $item): ?>
        <div class="feed-item feed-item--dn js-feed-item" data-uid="<?= $item->uniqueid; ?>">
          <h2 class="feed-item__title">
            <span class="feed-item__new-tag">New!</span>
            <a class="feed-item__link js-link" href="<?= $item->source->absoluteUrl; ?>"><?= $item->title; ?></a>
          </h2>
          <div class="feed-item__meta">
            <a class="feed-item__link js-peak" href="<?= $item->source->sourceUrl; ?>" title="Peak at the comments">⦿</a>
            <a class="feed-item__link" href="<?= $item->source->sourceUrl; ?>"><?= $item->source->commentsCount; ?> comments</a>
            -
            <a class="feed-item__link" href="<?= $item->source->sourceUrl; ?>"><?= $item->source->likesCount; ?> points</a>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="feed feed--hn">
      <header class="feed-header feed-header--hn">Hacker News</header>
      <?php foreach($hn_feed as $item): ?>
        <div class="feed-item feed-item--hn js-feed-item" data-uid="<?= $item->uniqueid; ?>">
          <h2 class="feed-item__title">
            <span class="feed-item__new-tag">New!</span>
            <a class="feed-item__link js-link" href="<?= $item->source->absoluteUrl; ?>"><?= $item->title; ?></a>
          </h2>
          <div class="feed-item__meta">
            <a class="feed-item__link js-peak" href="<?= $item->source->sourceUrl; ?>" title="Peak at the comments">⦿</a>
            <a class="feed-item__link" href="<?= $item->source->sourceUrl; ?>"><?= $item->source->commentsCount; ?> comments</a>
            -
            <a class="feed-item__link" href="<?= $item->source->sourceUrl; ?>"><?= $item->source->likesCount; ?> points</a>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <footer class="footer">
    <a href="http://github.com/anthonyec">
      <img src="https://avatars1.githubusercontent.com/u/1451668?s=460&v=4" alt="anthonyec">
    </a>
  </footer>

  <div class="peak peak--hidden js-peak-overlay">
    <div class="peak__frame-container">
      <iframe src="about:blank" frameborder="0" class="peak__iframe js-peak-frame"></iframe>
    </div>
  </div>

  <script>
    (function() {
      const $items = document.querySelectorAll('.js-feed-item');
      const $peakLinks = document.querySelectorAll('.js-peak');
      const $peakOverlay = document.querySelector('.js-peak-overlay');
      const $peakFrame = document.querySelector('.js-peak-frame');

      let $hoverItem = null;

      function openPeak(url) {
        $peakOverlay.classList.remove('peak--hidden');
        $peakFrame.src = 'peak.php?url=' + url;
      }

      function closePeak() {
        $peakOverlay.classList.add('peak--hidden');
        $peakFrame.src = 'about:blank';
      }

      function openPeakForItem($item) {
        const $peakLink = $item.querySelector('.js-peak');
        openPeak($peakLink.href);
      }

      function gotoLinkFromItem($item) {
        const $link = $item.querySelector('.js-link');
        // window.location = $link.href;
        window.open($link.href, '_blank');
      }

      function onPeakLinkClick(evt) {
        evt.preventDefault();
        openPeak(evt.currentTarget.href);
      }

      function onMouseEnterItem(evt) {
        $hoverItem = evt.currentTarget;
      }

      function onMouseLeaveItem(evt) {
        $hoverItem = null;
      }

      $peakLinks.forEach(($link) => {
        $link.addEventListener('click', onPeakLinkClick);
      });

      $items.forEach(($item) => {
        $item.addEventListener('mouseenter', onMouseEnterItem)
        $item.addEventListener('mouseleave', onMouseLeaveItem)
      })

      document.body.addEventListener('keydown', (evt) => {
        if (evt.keyCode === 27) {
          closePeak();
        }

        if (evt.keyCode === 32 && $hoverItem !== null) {
          evt.preventDefault();
          openPeakForItem($hoverItem);
        }

        if (evt.keyCode === 13 && $hoverItem !== null) {
          evt.preventDefault();
          gotoLinkFromItem($hoverItem);
        }
      });
      $peakOverlay.addEventListener('click', closePeak);
    })();
  </script>

  <script>
    (function() {
      const $feedItems = document.querySelectorAll('.js-feed-item');
      const items = Array.prototype.slice.call($feedItems); // nodelist to array
      const oldsUids = JSON.parse(localStorage.getItem('oldsUids')) || [];

      const uids = items.map(($item) => {
        return $item.dataset.uid;
      });

      const newUids = uids.filter((item) => {
        return oldsUids.indexOf(item) < 0;
      });

      if (oldsUids.length && newUids.length) {
        newUids.forEach((uid) => {
          const $newItem = document.querySelector(`[data-uid="${uid}"`);
          $newItem.classList.add('feed-item--new');
        });

        const title = `(${newUids.length}) ${document.title}`;
        document.title = title;
      }

      const combinedUids = oldsUids.concat(newUids);
      localStorage.setItem('oldsUids', JSON.stringify(combinedUids));
    })();
  </script>
  </body>
</html>
