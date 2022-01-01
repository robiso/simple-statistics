<?php

use Jaybizzle\CrawlerDetect\CrawlerDetect;

include_once __DIR__ . '/lib/CrawlerDetect.php';

global $Wcms;

class SimpleStatistics {
    private $Wcms;

    private $db;

    private $dbPath = __DIR__ . '/../../data/';
    private $dbFile = 'simplestatistics.json';

    private $CrawlerDetect;

    public function __construct($load) {
        if ($load) {
            global $Wcms;
            $this->Wcms =&$Wcms;
        }

        $this->CrawlerDetect = new CrawlerDetect();
    }

    public function init(): void {
        $before = memory_get_usage();
        $this->db = $this->getDb();
        $after = memory_get_usage();
        $size = $after - $before;

        if ($size > 550000) {
            rename($this->dbPath . $this->dbFile, $this->dbPath . date('Ymdhis-') . $this->dbFile);
            $this->init();
        }
    }

    private function getDb(): stdClass {
        if (! file_exists($this->dbPath . $this->dbFile)) {
            file_put_contents($this->dbPath . $this->dbFile, json_encode([
                    'pageviews' => [],
            ], JSON_FORCE_OBJECT | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        }

        return json_decode(file_get_contents($this->dbPath . $this->dbFile));
    }

    public function attach(): void {
        $this->Wcms->addListener('js', [$this, 'collectData']);
        $this->Wcms->addListener('settings', [$this, 'alterAdmin']);
    }

    private function save(): void {
        file_put_contents($this->dbPath . $this->dbFile,
                json_encode($this->db, JSON_FORCE_OBJECT | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
    }

    public function set(): void {
        $numArgs = func_num_args();
        $args = func_get_args();

        switch ($numArgs) {
                case 2:
                    $this->db->{$args[0]} = $args[1];
                    break;
                case 3:
                    $this->db->{$args[0]}->{$args[1]} = $args[2];
                    break;
                case 4:
                    $this->db->{$args[0]}->{$args[1]}->{$args[2]} = $args[3];
                    break;
                case 5:
                    $this->db->{$args[0]}->{$args[1]}->{$args[2]}->{$args[3]} = $args[4];
                    break;
            }
        $this->save();
    }

    public function get() {
        $numArgs = func_num_args();
        $args = func_get_args();
        switch ($numArgs) {
                case 1:
                    return $this->db->{$args[0]};
                case 2:
                    return $this->db->{$args[0]}->{$args[1]};
                case 3:
                    return $this->db->{$args[0]}->{$args[1]}->{$args[2]};
                case 4:
                    return $this->db->{$args[0]}->{$args[1]}->{$args[2]}->{$args[3]};
                case 5:
                    return $this->db->{$args[0]}->{$args[1]}->{$args[2]}->{$args[3]}->{$args[4]};
            }
    }

    public function injectJs(array $args): array {
        if (! $this->Wcms->loggedIn) {
            return $args;
        }

        return $args[0];
    }

    public function collectData(array $args): array {
        if ($this->Wcms->loggedIn) {
            return $args;
        }
        if (in_array($this->Wcms->currentPage, ['favicon-ico', 'robots-txt'])) {
            return $args;
        }
        if ($this->CrawlerDetect->isCrawler()) {
            return $args;
        }

        $count = @$this->get('pageviews', date('d-m-Y'), $this->Wcms->currentPage);
        if (! $count) {
            $count = 0;
        }
        $count++;
        @$this->set('pageviews', date('d-m-Y'), $this->Wcms->currentPage, $count);

        $count = @$this->get('sessions', date('d-m-Y'), session_id());
        if (! $count) {
            $count = 0;
        }
        $count++;
        @$this->set('sessions', date('d-m-Y'), session_id(), $count);

        return $args;
    }

    public function alterAdmin(array $args): array {
        $doc = new DOMDocument();
        @$doc->loadHTML($args[0]);
        @$doc->loadHTML(mb_convert_encoding($args[0], 'HTML-ENTITIES', 'UTF-8'));

        $menuItem = $doc->createElement('li');
        $menuItem->setAttribute('class', 'nav-item');
        $menuItemA = $doc->createElement('a');
        $menuItemA->setAttribute('href', '#stats');
        $menuItemA->setAttribute('aria-controls', 'stats');
        $menuItemA->setAttribute('role', 'tab');
        $menuItemA->setAttribute('data-toggle', 'tab');
        $menuItemA->setAttribute('class', 'nav-link');
        $menuItemA->nodeValue = 'Statistics';
        $menuItem->appendChild($menuItemA);

        $doc->getElementById('currentPage')->parentNode->parentNode->childNodes->item(1)->appendChild($menuItem);

        $wrapper = $doc->createElement('div');
        $wrapper->setAttribute('role', 'tabpanel');
        $wrapper->setAttribute('class', 'tab-pane');
        $wrapper->setAttribute('id', 'stats');

        // Contents of wrapper

        $h2 = $doc->createElement('h2');
        $h2->nodeValue = 'Today';
        $h2->setAttribute('style', 'text-align:center; color: #ddd');
        $wrapper->appendChild($h2);

        $table = $doc->createElement('table');
        $table->setAttribute('style', 'width:100%');
        $tr = $doc->createElement('tr');

        // Pageviews today
        $count = @$this->get('pageviews', date('d-m-Y'), $this->Wcms->currentPage);
        if (! $count) {
            $count = 0;
        }

        $td = $doc->createElement('td');
        $td->setAttribute('style', 'text-align:center; width:33%');
        $b = $doc->createElement('b');
        $b->nodeValue = 'Pageviews';
        $td->appendChild($b);
        $div = $doc->createElement('div');
        $div->setAttribute('style', 'font-size:2em');
        $div->nodeValue = $count;
        $td->appendChild($div);
        $tr->appendChild($td);

        // Sessions today
        $count = @$this->get('sessions', date('d-m-Y'));
        $count = count((array) $count);

        $td = $doc->createElement('td');
        $td->setAttribute('style', 'text-align:center; width:33%');
        $b = $doc->createElement('b');
        $b->nodeValue = 'Sessions';
        $td->appendChild($b);
        $div = $doc->createElement('div');
        $div->setAttribute('style', 'font-size:2em');
        $div->nodeValue = $count;
        $td->appendChild($div);
        $tr->appendChild($td);

        // Most popular page
        $pages = (array) @$this->get('pageviews', date('d-m-Y'));
        asort($pages);
        $pages = array_keys($pages);
        $count = array_pop($pages);

        $td = $doc->createElement('td');
        $td->setAttribute('style', 'text-align:center; width:33%');
        $b = $doc->createElement('b');
        $b->nodeValue = 'Most popular page';
        $td->appendChild($b);
        $div = $doc->createElement('div');
        $div->setAttribute('style', 'font-size:2em');
        $div->nodeValue = $count;
        $td->appendChild($div);
        $tr->appendChild($td);

        $table->appendChild($tr);
        $wrapper->appendChild($table);

        $h2 = $doc->createElement('h2');
        $h2->nodeValue = 'This week';
        $h2->setAttribute('style', 'text-align:center; margin-top:2em; color: #ddd');
        $wrapper->appendChild($h2);

        $table = $doc->createElement('table');
        $table->setAttribute('style', 'width:100%');
        $tr = $doc->createElement('tr');

        // Pageviews this week
        $total = 0;
        for ($i = 0; $i < 7; $i++) {
            $pages = @$this->get('pageviews', date('d-m-Y', strtotime("last monday + $i days")));
            if (! $pages) {
                $pages = [];
            }
            $count = 0;
            foreach ($pages as $page) {
                $count += $page;
            }
            if (! $count) {
                $count = 0;
            }
            $total += $count;
        }

        $td = $doc->createElement('td');
        $td->setAttribute('style', 'text-align:center; width:33%');
        $b = $doc->createElement('b');
        $b->nodeValue = 'Pageviews';
        $td->appendChild($b);
        $div = $doc->createElement('div');
        $div->setAttribute('style', 'font-size:2em; color: #ddd');
        $div->nodeValue = $total;
        $td->appendChild($div);
        $tr->appendChild($td);

        // Sessions this week
        $total = 0;
        for ($i = 0; $i < 7; $i++) {
            $count = @$this->get('sessions', date('d-m-Y', strtotime("last monday + $i days")));
            $count = count((array) $count);
            if (! $count) {
                $count = 0;
            }
            $total += $count;
        }

        $td = $doc->createElement('td');
        $td->setAttribute('style', 'text-align:center; width:33%');
        $b = $doc->createElement('b');
        $b->nodeValue = 'Sessions';
        $td->appendChild($b);
        $div = $doc->createElement('div');
        $div->setAttribute('style', 'font-size:2em; color: #ddd');
        $div->nodeValue = $total;
        $td->appendChild($div);
        $tr->appendChild($td);

        // Most popular page
        $total = [];
        for ($i = 0; $i < 7; $i++) {
            $pages = (array) @$this->get('pageviews', date('d-m-Y', strtotime("last monday + $i days")));
            foreach ($pages as $page => $views) {
                if (! isset($total[$page])) {
                    $total[$page] = 0;
                }
                $total[$page] += $views;
            }
        }
        asort($total);
        $total = array_keys($total);
        $count = array_pop($total);

        $td = $doc->createElement('td');
        $td->setAttribute('style', 'text-align:center; width:33%');
        $b = $doc->createElement('b');
        $b->nodeValue = 'Most popular page';
        $td->appendChild($b);
        $div = $doc->createElement('div');
        $div->setAttribute('style', 'font-size:2em; ');
        $div->nodeValue = $count;
        $td->appendChild($div);
        $tr->appendChild($td);

        $table->appendChild($tr);
        $wrapper->appendChild($table);

        // Todo: use graphing lib or create class for these graphs

        // Little graph for pageviews

        $h2 = $doc->createElement('h2');
        $h2->nodeValue = 'Pageviews last two weeks';
        $h2->setAttribute('style', 'text-align:center; margin-top:2em; margin-bottom:1em; color: #ddd');
        $wrapper->appendChild($h2);

        $graph = $doc->createElement('table');
        $graph->setAttribute('style', 'width:100%; border-collapse:collapse');
        $tr = $doc->createElement('tr');
        $labels = $doc->createElement('tr');

        $data = [];

        for ($i = -13; $i <= 0; $i++) {
            $date = date('d-m-Y', strtotime("+$i days"));
            $pages = @$this->get('pageviews', $date) ? $this->get('pageviews', $date) : [];
            $count = 0;
            foreach ($pages as $page) {
                $count += $page;
            }
            $data[$date] = $count;
        }
        foreach ($data as $day => $count) {
            $td = $doc->createElement('td');
            $td->setAttribute('style', 'vertical-align:bottom; width:calc(100% / 14)');
            $div = $doc->createElement('div');
            $height = 0;
            if (max($data) > 0) {
                $height = $count / max($data) * 150;
            }
            $div->setAttribute('style', "position:relative; height:{$height}px; width:100%; background:#1ab; color:#fff; text-align:center");
            if ($height > 18) {
                $div->nodeValue = $count;
            } else {
                $label = $doc->createElement('div');
                $label->nodeValue = $count;
                $label->setAttribute('style', 'position: relative; top:-1.5em; color:#aaa');
                $div->appendChild($label);
            }
            $td->appendChild($div);
            $tr->appendChild($td);

            $td = $doc->createElement('td');
            $td->setAttribute('style', 'border-top: 1px solid #aaa; padding-top:0.5em; transform:rotate(35deg) translate(23px, 1px)');
            $td->nodeValue = date('j M', strtotime($day));
            $labels->appendChild($td);
        }
        $graph->appendChild($tr);
        $graph->appendChild($labels);
        $wrapper->appendChild($graph);

        // Little graph for sessions

        $h2 = $doc->createElement('h2');
        $h2->nodeValue = 'Sessions last two weeks';
        $h2->setAttribute('style', 'text-align:center; margin-top:2em; margin-bottom:1em; color: #ddd');
        $wrapper->appendChild($h2);

        $graph = $doc->createElement('table');
        $graph->setAttribute('style', 'width:100%; border-collapse:collapse;');
        $tr = $doc->createElement('tr');
        $labels = $doc->createElement('tr');

        $data = [];

        for ($i = -13; $i <= 0; $i++) {
            $date = date('d-m-Y', strtotime("+$i days"));
            $sessions = @$this->get('sessions', $date) ? (array) $this->get('sessions', $date) : [];
            $data[$date] = count($sessions);
        }
        foreach ($data as $day => $count) {
            $td = $doc->createElement('td');
            $td->setAttribute('style', 'vertical-align:bottom; width:calc(100% / 14)');
            $div = $doc->createElement('div');
            $height = 0;
            if (max($data) > 0) {
                $height = $count / max($data) * 150;
            }
            $div->setAttribute('style', "position:relative; height:{$height}px; width:100%; background:#1ab; color:#fff; text-align:center");
            if ($height > 18) {
                $div->nodeValue = $count;
            } else {
                $label = $doc->createElement('div');
                $label->nodeValue = $count;
                $label->setAttribute('style', 'position: relative; top:-1.5em; color:#aaa');
                $div->appendChild($label);
            }
            $td->appendChild($div);
            $tr->appendChild($td);

            $td = $doc->createElement('td');
            $td->setAttribute('style', 'border-top: 1px solid #aaa; padding-top:0.5em; transform:rotate(35deg) translate(23px, 1px)');
            $td->nodeValue = date('j M', strtotime($day));
            $labels->appendChild($td);
        }
        $graph->appendChild($tr);
        $graph->appendChild($labels);
        $wrapper->appendChild($graph);

        // End of contents of wrapper

        $doc->getElementById('currentPage')->parentNode->appendChild($wrapper);

        $args[0] = preg_replace('~<(?:!DOCTYPE|/?(?:html|body))[^>]*>\s*~i', '', $doc->saveHTML());

        return $args;
    }
}
