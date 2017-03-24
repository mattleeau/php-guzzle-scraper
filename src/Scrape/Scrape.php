<?php
namespace Scrape;

use \DOMXPath;
use \DOMDocument;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;

class Scrape
{

    private $webClient;

    private $dom;

    public function __construct($site, $timeout = 2)
    {
        $this->webClient = new Client(['base_uri' => $site, 'timeout' => $timeout]);
    }

    public function load($page) {

        try {
            $response = $this->webClient->get($page);
        } catch(ConnectException $e) {
            throw new \RuntimeException($e->getHandlerContext()['error']);
        }

        $html = $response->getBody();

        $this->dom = new DOMDocument;

        // Ignore errors caused by unsupported HTML5 tags
        libxml_use_internal_errors(true);
        $this->dom->loadHTML($html);
        libxml_clear_errors();

        return $this;
    }

    public function getNode($xpath, $parent=null) {
        $nodes = $this->getNodes($xpath, $parent);

        if ($nodes->length === 0) {
            //throw new \RuntimeException("No matching node found");
            return null;
        }

        return $nodes[0];
    }

    public function getNodes($xpath, $parent=null) {
        $DomXpath = new DOMXPath($this->dom);
        $nodes = $DomXpath->query($xpath, $parent);
        return $nodes;
    }

    static public function run($website_url, $wait=5, $max_result_per_author=5, $concurrency=null) {

        $data = array();

        $scraper = new Scrape($website_url, $wait);
        $scraper->load('/');

        $article_url_node_list = $scraper->getNodes('//h2[@class="headline"]/a[@class="url"]');

        foreach ($article_url_node_list as $url_node) {
            if ($url_node) {
                $articleUrl = $url_node->getAttribute('href');
                $article_scraper = new Scrape($website_url, $wait);
                $article_scraper->load('/' . $articleUrl);
                $article_div_main_content = $article_scraper->getNode('//div[@class="main-content"]');
                $article_div_section_2 = $article_scraper->getNode('./div[@id="section-2"]', $article_div_main_content);

                // get article element
                $article_ele = $article_scraper->getNode('./div[@class="box1 article article-show"]/div[@class="records"]/div[@class="record"]', $article_div_section_2);

                // initialize variables
                $article_title = '';
                $article_date = '';
                $article_url = '';
                $author_name = '';
                $author_url = '';
                $author_bio = '';
                $author_twitter_handle = '';

                if ($article_ele) {
                    // Article Title
                    $article_title_node = $article_scraper->getNode('./h1', $article_ele);
                    if ($article_title_node)
                        $article_title = $article_title_node->nodeValue;

                    // Article Date
                    $article_date_node = $article_scraper->getNode('./div[@class="meta clearfix"]/div[@class="date"]', $article_ele);
                    if ($article_date_node)
                        $article_date = date('Y-m-d', strtotime($article_date_node->nodeValue));

                    // Article URL
                    $article_url = $website_url . $articleUrl;
                }

                // get author element
                $author_ele = $article_scraper->getNode('./div[@class="box3 article recent-articles"]/div[@class="article-author-bio"]/div[@class="records"]/div[@class="record clearfix"]/div[@class="author-info"]', $article_div_section_2);
                if ($author_ele) {
                    // Author Name
                    $author_name_node = $article_scraper->getNode('./h2/a', $author_ele);
                    if ($author_name_node)
                        $author_name = $author_name_node->nodeValue;

                    // Author URL
                    $author_url_node = $article_scraper->getNode('./h2/a', $author_ele);
                    if ($author_url_node)
                        $author_url = $website_url . $author_url_node->getAttribute('href');

                    // Author Bio
                    $author_bio_node = $article_scraper->getNode('./div[@class="author_bio"]', $author_ele);
                    if ($author_bio_node)
                        $author_bio = $author_bio_node->nodeValue;

                    // Author Twitter Handle
                    $author_twitter_handle_node = $article_scraper->getNode('./div[@class="author_bio"]/a', $author_ele);
                    if ($author_twitter_handle_node)
                        $author_twitter_handle = $author_twitter_handle_node->getAttribute('href');
                }

                if ($author_name) {
                    if (!isset($data[$author_name]['article']) || count($data[$author_name]['article']) < $max_result_per_author) {
                        $data[$author_name]['article'][] = array(
                            'articleTitle' => $article_title,
                            'articleUrl' => $article_url,
                            'articleDate' => $article_date,
                        );
                    }
                    $data[$author_name]['authorName'] = $author_name;
                    $data[$author_name]['authorTwitterHandle'] = $author_twitter_handle;
                    $data[$author_name]['authorBio'] = $author_bio;
                    $data[$author_name]['authorURL'] = $author_url;
                }

            }
        }

        return $data;
    }
}