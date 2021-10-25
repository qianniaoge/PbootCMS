<?php
/**
 * @copyright (C)2016-2099 Hnaoyun Inc.
 * @author XingMeng
 * @email hnxsh@foxmail.com
 * @date 2018年7月15日
 *  生成sitemap文件
 */
namespace app\home\controller;

use core\basic\Controller;
use app\home\model\SitemapModel;
use core\basic\Url;

class SitemapController extends Controller
{

    protected $model;

    public function __construct()
    {
        $this->model = new SitemapModel();
    }

    public function index()
    {
        header("Content-type:text/xml;charset=utf-8");
        $str = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $str .= '<urlset>' . "\n";
        $str .= $this->makeNode('', date('Y-m-d'), '1.00'); // 根目录
        
        $sorts = $this->model->getSorts();
        $Parser = new ParserController();
        foreach ($sorts as $value) {
            if ($value->outlink) {
                continue;
            } elseif ($value->type == 1) {
                $link = $Parser->parserLink(1, $value->urlname, 'about', $value->scode, $value->filename);
                $str .= $this->makeNode($link, date('Y-m-d'), '0.80');
            } else {
                $link = $Parser->parserLink(2, $value->urlname, 'list', $value->scode, $value->filename);
                $str .= $this->makeNode($link, date('Y-m-d'), '0.80');
                $contents = $this->model->getSortContent($value->scode);
                foreach ($contents as $value2) {
                    if ($value2->outlink) { // 外链
                        continue;
                    } else {
                        $link = $Parser->parserLink(2, $value2->urlname, 'content', $value2->scode, $value2->sortfilename, $value2->id, $value2->filename);
                    }
                    $str .= $this->makeNode($link, date('Y-m-d'), '0.60');
                }
            }
        }
        echo $str . "\n</urlset>";
    }

    // 生成结点信息
    private function makeNode($link, $date, $priority = 0.60)
    {
        $node = '
<url>
    <loc>' . get_http_url() . $link . '</loc>
    <priority>' . $priority . '</priority>
    <lastmod>' . $date . '</lastmod>
    <changefreq>Always</changefreq>
</url>';
        return $node;
    }

    // 文本格式
    public function linkTxt()
    {
        $sorts = $this->model->getSorts();
        $Parser = new ParserController();
        $str = '';
        foreach ($sorts as $value) {
            if ($value->outlink) {
                continue;
            } elseif ($value->type == 1) {
                $link = $Parser->parserLink(1, $value->urlname, 'about', $value->scode, $value->filename);
            } else {
                $link = $Parser->parserLink(2, $value->urlname, 'list', $value->scode, $value->filename);
                $str .= get_http_url() . $link . "\n";
                $contents = $this->model->getSortContent($value->scode);
                foreach ($contents as $value2) {
                    if ($value2->outlink) { // 外链
                        continue;
                    } else {
                        $link = $Parser->parserLink(2, $value2->urlname, 'content', $value2->scode, $value2->sortfilename, $value2->id, $value2->filename);
                    }
                    $str .= get_http_url() . $link . "\n";
                }
            }
        }
        echo $str;
    }
}