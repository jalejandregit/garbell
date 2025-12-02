<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class garbell_Scraper {
    private $startUrl;
    private $maxDepth;
    private $maxPages;
    private $selectorPages;
    private $tagsExclusions;
    private $selectorEntries;
    private $selectorImages;
    private $visited = array();
    private $validPages = array();
    private $existingLinkPost = array();
    private $start_host;
    private $timeout;
    //noves
    private $valid = array();   // URLs válidas (nuevas) — únicas

    public function __construct($opts = array()) {
        /*
       // 1) LOG para depurar (registro de WP)
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("garbell_Scraper __construct opts: " . print_r($opts, true));
        }

        // 2) Comprobación robusta / sin usar empty() si '0' puede ser válido
        if (! array_key_exists('selectorImages', $opts) ) {
            throw new ValueError("selectorImages no existe en options");
        }

        if ($opts['selectorImages'] === null || $opts['selectorImages'] === '') {
            // Lanzar si está realmente vacío
            throw new ValueError("selectorImages no puede estar vacío");
        }
       */


        $this->startUrl = $opts['startUrl'];
        $this->maxDepth = isset($opts['maxDepth']) ? intval($opts['maxDepth']) : 2;
        $this->maxPages = isset($opts['maxPages']) ? intval($opts['maxPages']) : 50;
        $this->selectorPages = !empty($opts['selectorPages']) ? $opts['selectorPages'] : '';
        $this->tagsExclusions = !empty($opts['tagsExclusions']) ? $opts['tagsExclusions'] : '';
        $this->selectorEntries = !empty($opts['selectorEntries']) ? $opts['selectorEntries'] : '';
        $this->selectorImages = !empty($opts['selectorImages']) ? $opts['selectorImages'] : '';

        $this->start_host = parse_url($this->startUrl, PHP_URL_HOST);
        $settings = get_option('garbell_settings', array());
        $this->timeout = isset($settings['maxExecutionSeconds']) ? intval($settings['maxExecutionSeconds']) : 5;
    }

    /**
     * Optional helper: read wp-config.php constants (if aún lo necesitas).
     * Not necessary — $wpdb cubre accesos.
     */
    public static function read_wp_config_db_constants() {
        $cfg = ABSPATH . 'wp-config.php';
        if (!file_exists($cfg)) return array();
        $contents = file_get_contents($cfg);
        preg_match("/define\(\s*'DB_NAME'\s*,\s*'([^']+)'/", $contents, $m1);
        preg_match("/define\(\s*'DB_USER'\s*,\s*'([^']+)'/", $contents, $m2);
        preg_match("/define\(\s*'DB_PASSWORD'\s*,\s*'([^']+)'/", $contents, $m3);
        preg_match("/define\(\s*'DB_HOST'\s*,\s*'([^']+)'/", $contents, $m4);
        return array(
            'DB_NAME' => isset($m1[1]) ? $m1[1] : '',
            'DB_USER' => isset($m2[1]) ? $m2[1] : '',
            'DB_PASSWORD' => isset($m3[1]) ? $m3[1] : '',
            'DB_HOST' => isset($m4[1]) ? $m4[1] : '',
        );
    }

    /**
     * Entry point
     */
    public function run() {
        // 4.1 Check robots.txt
        $robotsAllowed = $this->check_robots();
        if (!$robotsAllowed) {
            return new WP_Error('robots_disallow', 'Robots.txt no permite scraping en este sitio.');
        }

        // 4.2 Build existingLinkPost from wp_postmeta
        global $wpdb;
        $domain_like = $wpdb->esc_like($this->startUrl) . '%';
        $rows = $wpdb->get_results($wpdb->prepare("SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = 'link_post' AND meta_value LIKE %s", $domain_like));
        foreach ($rows as $r) {
            $this->existingLinkPost[] = $r->meta_value;
        }

        // Start crawl recursively
        $this->crawl($this->startUrl, 0);


         // limitar y asegurar unicidad
        $this->validPages = array_values(array_unique($this->validPages));
        if (count($this->validPages) > $this->maxPages) {
            $this->validPages = array_slice($this->validPages, 0, $this->maxPages);
        }

        // Para cada página válida, extraiga los campos.
        $postsData = array();
        foreach ($this->validPages as $url) {
            $d = $this->extract_post_data($url);
            if ($d) $postsData[] = $d;
        }

        return array('validPages' => $this->validPages, 'postsData' => $postsData);
    }

    /**
     * Check robots.txt - allow/disallow for User-agent: *
     */
    private function check_robots() {
        $robots_url = rtrim(parse_url($this->startUrl, PHP_URL_SCHEME) . '://' . $this->start_host, '/') . '/robots.txt';
        $resp = wp_remote_get($robots_url, array('timeout' => 5));
        if (is_wp_error($resp)) {
            // if robots not reachable assume allowed
            return true;
        }
        $body = wp_remote_retrieve_body($resp);
        if (empty($body)) return true;

        // Simple robots parser - check for Disallow: / for User-agent: *
        $lines = preg_split("/\r\n|\n|\r/", $body);
        $ua = null;
        $disallows = array();
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || strpos($line, '#') === 0) continue;
            if (stripos($line, 'User-agent:') === 0) {
                $ua = trim(substr($line, strlen('User-agent:')));
            } elseif (stripos($line, 'Disallow:') === 0 && ($ua === '*' || $ua === null)) {
                $path = trim(substr($line, strlen('Disallow:')));
                if ($path === '/') {
                    return false;
                }
                $disallows[] = $path;
            }
        }

        // check start path against disallows
        $start_path = parse_url($this->startUrl, PHP_URL_PATH);
        foreach ($disallows as $d) {
            if ($d === '') continue;
            if (strpos($start_path, $d) === 0) return false;
        }
        return true;
    }

    /**
     * Crawl recursively (BFS-ish limited by maxDepth & maxPages)
     */
    private function crawl($url, $depth) {
        if ($depth > $this->maxDepth) return;
        if (count($this->validPages) >= $this->maxPages) return;


        $normalized = $this->normalize_url($url);
        if (isset($this->visited[$normalized])) return;
        $this->visited[$normalized] = true;

        // fetch page
        $resp = wp_remote_get($url, array('timeout' => max(5, $this->timeout)));
        if (is_wp_error($resp)) return;
        $body = wp_remote_retrieve_body($resp);
        if (empty($body)) return;

        // if page already exists in existingLinkPost skip adding to valid
        foreach ($this->existingLinkPost as $existingUrl) {
            if (strpos($normalized, $this->normalize_url($existingUrl)) === 0) {
                // domain/subdomain already has this url: skip
                return;
            }
        }

        // Extract potential post URLs using selectorEntries or by heuristics
        $doc = new \DOMDocument();
        libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="utf-8" ?>' . $body);
        libxml_clear_errors();
        $xpath = new \DOMXPath($doc);

        // If selectorEntries is provided, treat this page as a candidate post if it matches
        $isPost = false;
        if (!empty($this->selectorEntries)) {
            try {
                $nodes = $xpath->query($this->selectorEntries);
                if ($nodes && $nodes->length > 0) {
                    $isPost = true;
                }
            } catch (Exception $e) {
                // ignore selector parse errors
            }
        }

        // If it's a post candidate and not in existingPost list, add to validPages
        if ($isPost) {
            if (count($this->validPages) > $this->maxPages) {
                $this->validPages = array_slice($this->validPages, 0, $this->maxPages);
            }
        }

        // If selectorPages provided, gather links using it, otherwise gather all anchors from same host
        $linkNodes = array();
        if (!empty($this->selectorPages)) {
            try {
                $q = $xpath->query($this->selectorPages);
                if ($q && $q->length > 0) {
                    foreach ($q as $n) {
                        // when the selector matches container elements, extract anchors inside
                        if ($n->nodeName === 'a') {
                            $linkNodes[] = $n;
                        } else {
                            $anchors = $n->getElementsByTagName('a');
                            foreach ($anchors as $a) $linkNodes[] = $a;
                        }
                    }
                }
            } catch (Exception $e) {
                // ignore
            }
        }

        // fallback: all anchors
        if (empty($linkNodes)) {
            $anchors = $doc->getElementsByTagName('a');
            foreach ($anchors as $a) $linkNodes[] = $a;
        }

        foreach ($linkNodes as $a) {
            $href = $a->getAttribute('href');
            if (empty($href)) continue;
            $abs = $this->resolve_url($href, $url);
            if ($abs === false) continue;
            $uhost = parse_url($abs, PHP_URL_HOST);
            // only crawl same host (domain/subdomain)
            if ($uhost !== $this->start_host) continue;
            /*
            if (!empty($this->tagsExclusion)) {
                $patterns = is_array($this->tagsExclusion)
                    ? $this->tagsExclusion
                    : preg_split('/\|/', $this->tagsExclusion);

                $exclude = false;
                foreach ($patterns as $p) {
                    $p = trim($p);
                    if ($p === '') continue;
                    // Buscar literal dentro de la URL, sin importar mayúsculas/minúsculas
                    if (stripos($abs, $p) !== false) {
                        $exclude = true;
                        break;
                    }
                }
                if ($exclude) continue;
            }
            */

            if (!empty($this->tagsExclusion)) {
                $patterns = is_array($this->tagsExclusion)
                    ? $this->tagsExclusion
                    : preg_split('/\|/', $this->tagsExclusion);

                $exclude = false;

                foreach ($patterns as $p) {
                    $p = trim($p);
                    if ($p === '') continue;

                    // Detectar si el patrón parece una expresión regular (contiene \d, \/, paréntesis, llaves, clases, o / / explícitos)
                    $looks_like_regex = false;
                    if (strpos($p, '\\d') !== false || strpos($p, '\\/') !== false) $looks_like_regex = true;
                    if (preg_match('/[()[\]{}.+*^$\\\\]/', $p)) $looks_like_regex = true; // caracteres típicos de regex
                    if ($p !== '' && $p[0] === '/' && substr($p, -1) === '/') $looks_like_regex = true; // ya es /regex/

                    if ($looks_like_regex) {
                        // Asegurar delimitadores si no los tiene (usar / como delimitador)
                        if (!($p !== '' && $p[0] === '/' && substr($p, -1) === '/')) {
                            $regex = '/'.str_replace('/', '\/', $p).'/';
                        } else {
                            $regex = $p;
                        }

                        // usar preg_match (case-insensitive)
                        if (@preg_match($regex.'i', $abs)) {
                            $exclude = true;
                            break;
                        }
                    } else {
                        // búsqueda literal (case-insensitive)
                        if (stripos($abs, $p) !== false) {
                            $exclude = true;
                            break;
                        }
                    }
                }

                if ($exclude) continue;
            }


            // add to recursion
            if (!isset($this->visited[$this->normalize_url($abs)])) {
                if (count($this->validPages) < $this->maxPages) {
                    // Verificación: si este enlace no está presente en existingLinkPost ni en validPages, añadir candidato
                    $alreadyExisting = false;
                    foreach ($this->existingLinkPost as $existingUrl) {
                        if (strpos($this->normalize_url($existingUrl), $this->normalize_url($abs)) !== false ||
                            strpos($this->normalize_url($abs), $this->normalize_url($existingUrl)) !== false ) {
                            $alreadyExisting = true; break;
                        }
                    }
                    if (!$alreadyExisting) {
                        // Una pequeña regla general: si el enlace parece ser una publicación
                        // (contiene fecha o .html), es preferible añadirlo como válido.
                        $lower = strtolower($abs);
                        if (preg_match('/\d{4}\/\d{2}\/\d{2}/', $abs) || strpos($lower,'.html') !== false) {
                            $this->validPages[] = $this->normalize_url($abs);
                        }
                    }
                }
                // continue crawling (but limit depth & pages)
                if ($depth+1 <= $this->maxDepth && count($this->validPages) < $this->maxPages) {
                    $this->crawl($abs, $depth+1);
                }
            }

            if (count($this->validPages) >= $this->maxPages) break;
        }
        // garantizar la singularidad de forma continua
        $this->validPages = array_values(array_unique($this->validPages));
    }

    /**
     * Extract fields from a post page
     * Returns associative array with keys:
     *   linkPost, linkTitlePost, linkAutor, linkAutorUrl, linkContent, linksImg (array full images), linksImgThumb (array thumbs)
     */
    private function extract_post_data($url) {
        $resp = wp_remote_get($url, array('timeout' => max(5, $this->timeout)));
        if (is_wp_error($resp)) return null;
        $body = wp_remote_retrieve_body($resp);
        if (empty($body)) return null;


        $doc = new \DOMDocument();
        libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="utf-8" ?>' . $body);
        libxml_clear_errors();
        $xpath = new \DOMXPath($doc);

                $data = array(
            'linkPost' => $url,
            'linkTitlePost' => '',
            'linkAutor' => '',
            'linkAutorUrl' => '',
            'linksImg' => array(),
            'linksImgThumb' => array(),
        );

        // Title: try <meta property="og:title">, <title>, h1
        $nodes = $xpath->query("//meta[@property='og:title']/@content");
        if ($nodes && $nodes->length) {
            $data['linkTitlePost'] = trim($nodes->item(0)->nodeValue);
        } else {
            $nodes = $xpath->query('//title');
            if ($nodes && $nodes->length) $data['linkTitlePost'] = trim($nodes->item(0)->textContent);
            else {
                $nodes = $xpath->query('//h1');
                if ($nodes && $nodes->length) $data['linkTitlePost'] = trim($nodes->item(0)->textContent);
            }
        }

        // Author and author URL heuristics: meta name="author", rel=author, .author links
        $nodes = $xpath->query("//meta[@name='author']/@content");
        if ($nodes && $nodes->length) {
            $data['linkAutor'] = trim($nodes->item(0)->nodeValue);
        } else {
            $nodes = $xpath->query("//a[contains(@rel,'author')]");
            if ($nodes && $nodes->length) {
                $data['linkAutor'] = trim($nodes->item(0)->textContent);
                $data['linkAutorUrl'] = $this->resolve_url($nodes->item(0)->getAttribute('href'), $url);
            } else {
                $nodes = $xpath->query("//*[contains(@class,'author') or contains(@class,'byline')]");
                if ($nodes && $nodes->length) {
                    $data['linkAutor'] = trim($nodes->item(0)->textContent);
                }
            }
            // trim author urtohijo de lia
           /* $nodes = $xpath->query("//*[contains(@class,'widget Profile') ]//*[contains(@class, 'widget-content')]");
            if ($nodes && $nodes->length) {
                
            // Seleccionar todos los enlaces dentro del widget Profile
            $links = $xpath->query(".//ul/li/a", $nodes->item(0));

            $autores = [];
            $autoresUrl = [];

            foreach ($links as $link) {
                $autores[] = trim($link->textContent);
                $autoresUrl[] = $this->resolve_url($link->getAttribute('href'), $url);
            }

            $data['linkAutor'] = $autores;
            $data['linkAutorUrl'] = $autoresUrl;
            }
            */
        }

        // Content: try common selectors or full body
        /*
        if (!empty($this->selectorEntries)) {
            try {
                $cNodes = $xpath->query($this->selectorEntries);
                if ($cNodes && $cNodes->length) {
                    $data['linkContent'] = $doc->saveHTML($cNodes->item(0));
                }
            } catch (Exception $e) {}
        }
        if (empty($data['linkContent'])) {
            // fallback to main article, entry-content
            //logall --> //*[@id="main"]
            
            //$cNodes = $xpath->query("//*[contains(@class,'post') or contains(@class,'entry') or contains(@class,'article')]");
            $cNodes = $xpath->query("//*[@id='main']");
      
            if ($cNodes && $cNodes->length) {
                $content = $cNodes->item(0)->textContent;
            } else {
                $content = $doc->documentElement->textContent;
            }

            $data['linkContent'] = trim(preg_replace('/\s+/', ' ', $content));

        }
        */
        //$data['linkContent'] = '';

        // Imágenes: buscar enlaces con la etiqueta img dentro para capturar href (grande) e img src (miniatura)
        //$anchorImgs = $xpath->query("//a[img]"); //Inicial, no serveix perquè porta totes les imatges
        //$anchorImgs = $xpath->query("//*[@id='main']//a[img]"); //logall OK --> elimina els que no estan dins del main
        //$anchorImgs = $xpath->query("//*[@id='main']//a[img]/*[not(@class='delayLoad')]"); //alturgell -> només porta els delayload
        //$anchorImgs = $xpath->query("//*[@itemprop='blogPost']//a[img]"); //alturgell + logall OK -> només porta els delayload

        $anchorImgs = array();
        
        if (!empty($this->selectorImages)) {
            try {
               $anchorImgs = $xpath->query($this->selectorImages);

            } catch (Exception $e) {
                $errorName = 'WARNING';
                $errorLevel = Monolog\Logger::WARNING;
            }
        }else{
              
            wp_send_json_error('selector imagen vacío', 400);
    
            $anchorImgs = $xpath->query("//*[@id='main']//a[img]");
            //$this->selectorImages = ""; //*[@itemprop='blogPost']//a[img]";
            //$anchorImgs = $xpath->query("");
        }


        //$anchorImgs = $xpath->query($this->selectorImages);
        //$anchorImgs = $xpath->query("//a[img]");
        //$anchorImgs = $xpath->query("//*[contains(@class, 'wp-block-image') or contains(@class, 'post-image') or contains(@class, 'entry-image') or contains(@class, 'article-image') or contains(@class, 'image') or contains(@class, 'thumb') or contains(@class, 'thumbnail') or contains(@class, 'post-thumb') or contains(@class, 'post-thumbnail') or contains(@class, 'featured-image') or contains(@class, 'featured-image-thumb') or contains(@class, 'featured-image-thumbnail') or contains(@class, 'aligncenter') or contains(@class, 'alignleft') or contains(@class, 'alignright')]");
        foreach ($anchorImgs as $a) {
            $img = $a->getElementsByTagName('img')->item(0);
            if (!$img) continue;
            $href = $this->resolve_url($a->getAttribute('href'), $url);
            $src = $this->resolve_url($img->getAttribute('src'), $url);
            if ($href) $data['linksImg'][] = $href;
            if ($src) $data['linksImgThumb'][] = $src;
        }

        // También recopila imágenes dentro del contenido que no estén dentro de un enlace.
        /*
        $imgNodes = $xpath->query("//img");
        foreach ($imgNodes as $img) {
            $src = $this->resolve_url($img->getAttribute('src'), $url);
            if ($src && !in_array($src, $data['linksImgThumb'])) {
                $data['linksImgThumb'][] = $src;
            }
        }
        */
        // deduplicate while preserving keys
        $data['linksImg'] = array_values(array_unique($data['linksImg']));
        $data['linksImgThumb'] = array_values(array_unique($data['linksImgThumb']));

        return $data;
    }

    /**
     * Normalize URLs - remove fragments and trailing slashes
     */
    private function normalize_url($u) {
        $p = parse_url($u);
        if ($p === false) return $u;
        $scheme = isset($p['scheme']) ? $p['scheme'] : 'http';
        $host = isset($p['host']) ? $p['host'] : '';
        $path = isset($p['path']) ? rtrim($p['path'], '/') : '';
        $query = isset($p['query']) ? ('?' . $p['query']) : '';
        return strtolower($scheme . '://' . $host . $path . $query);
    }

    private function resolve_url($href, $base) {
        // handle protocol-relative
        if (strpos($href, '//') === 0) {
            $scheme = parse_url($base, PHP_URL_SCHEME);
            return $scheme . ':' . $href;
        }
        // absolute?
        if (parse_url($href, PHP_URL_SCHEME) !== null) {
            return $href;
        }
        // data: skip
        if (strpos($href, 'data:') === 0) return false;
        // anchors
        if (strpos($href, '#') === 0) return false;

        // relative path
        return wp_normalize_path( rtrim(dirname($base), '/') . '/' . ltrim($href, '/') );
    }
}
