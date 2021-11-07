<?php
/**
 * @copyright (C)2016-2099 Hnaoyun Inc.
 * @author XingMeng
 * @email hnxsh@foxmail.com
 * @date 2018年2月14日
 *  首页控制器
 */
namespace app\home\controller;

use core\basic\Controller;
use core\basic\Config;
use core\basic\Url;

class IndexController extends GetPageController
{

    // 空拦截器, 实现文章路由转发
    public function _empty()
    {
        // 地址类型
        $url_rule_type = $this->config('url_rule_type') ?: 3;
        
        if (P) { // 采用pathinfo模式及p参数伪静态模式
            if ($url_rule_type == 2 && stripos(URL, $_SERVER['SCRIPT_NAME']) !== false) { // 禁止伪静态时带index.php访问
                _404('您访问的内容不存在，请核对后重试！');
            }
            $path = P;
        } elseif ($url_rule_type > 2 && isset($_SERVER["QUERY_STRING"]) && $qs = $_SERVER["QUERY_STRING"]) { // 采用简短传参模式
            $qs = ltrim($qs, '/');
            parse_str($qs, $output);
            unset($output['page']); // 去除分页
            if ($output && ! current($output)) { // 第一个路径参数不能有值，否则非标准路径参数
                $path = key($output); // 第一个参数为路径信息，注意PHP数组会自动将key点符号转换下划线
            } elseif (get('tag')) { // 对于兼容模式tag需要自动跳转tag独立页面
                $tag = new TagController();
                $tag->index();
            } elseif (get('keyword')) { // 兼容模式搜索处理
                $search = new SearchController();
                $search->index();
            }
        }
        
        // 判断是否存在后缀
        $url_rule_suffix = substr($this->config('url_rule_suffix'), 1);
        $suffix = false;
        $slash = false;
        if (preg_match('/(.*)(_|\.)' . $url_rule_suffix . '$/', $path, $matchs)) {
            $path = $matchs[1];
            $suffix = true;
        } elseif (preg_match('/^[\w\-\/]+\/$/', $path)) {
            $slash = true;
            $path = trim($path, '/');
        }
        $path_arr = $path ? explode('/', $path) : array();
        
        // 开始路由
        if (isset($path_arr) && count($path_arr) > 0) {
            switch (strtolower($path_arr[0])) {
                case 'search':
                case 'keyword':
                    $search = new SearchController();
                    $search->index();
                    break;
                case 'message':
                    $msg = new MessageController();
                    $msg->index();
                    break;
                case 'form':
                    $_GET['fcode'] = $path_arr[1];
                    $form = new FormController();
                    $form->index();
                    break;
                case 'sitemap':
                case 'sitemap_xml':
                    $sitemap = new SitemapController();
                    $sitemap->index();
                    break;
                case 'sitemap_txt':
                    $sitemap = new SitemapController();
                    $sitemap->linkTxt();
                    break;
                case 'tag':
                    $tag = new TagController();
                    $tag->index();
                    break;
                case 'member':
                    $member = new MemberController();
                    $member->{$path_arr[1]}();
                    break;
                case 'comment':
                    $comment = new CommentController();
                    $comment->{$path_arr[1]}();
                    break;
                case 'spider':
                    $spider = new SpiderController();
                    $spider->index();
                    break;
                default:
                    
                    $url_break_char = $this->config('url_break_char') ?: '_';
                    $url_rule_content_path = $this->config('url_rule_content_path') ? true : false;
                    $err = '';
                    $iscontent = false;
                    
                    // 开始进行地址匹配
                    if (! $suffix && ! ! $sort = $this->model->getSort($path)) {
                        // 栏目名称，即栏目全路径匹配
                    } elseif (preg_match('/^([a-zA-Z0-9\-\/]+)' . $url_break_char . '([0-9]+)$/i', $path, $matchs) && ! ! $sort = $this->model->getSort($matchs[1])) {
                        // 栏目名称_分页，栏目分页的情况
                        // define('CMS_PAGE_CUSTOM', true); // 设置走自定义CMS分页
                        $_GET['page'] = $matchs[2]; // 设置分页参数
                    } else {
                        
                        if ($url_rule_content_path && ! ! $data = $this->model->getContent($path)) {
                            $iscontent = true; // 短路径情况
                        } elseif (! $url_rule_content_path) {
                            // 详情页至少是2级，对地址进行栏目和内容路径拆分，访问详情页
                            $part1 = dirname($path);
                            $part2 = basename($path);
                            while ($part1 != '.') {
                                if ((! ! $sort = $this->model->getSort($part1)) && ! ! $data = $this->model->getContent($part2)) {
                                    // 栏目名称/内容名称或ID
                                    $iscontent = true;
                                    $scode = $sort->scode;
                                    break;
                                } elseif (preg_match('/^([a-zA-Z0-9\-\/]+)' . $url_break_char . '([0-9]+)$/i', $part1, $matchs) && ! ! $model = $this->model->checkModelUrlname($matchs[1])) {
                                    // 模型名称_栏目ID/内容名称或ID
                                    $data = $this->model->getContent($part2);
                                    $iscontent = true;
                                    $scode = $matchs[2];
                                    // 限制串模型多路径
                                    if (! ! $data->urlname && $matchs[1] != $data->urlname) {
                                        $err = true;
                                    }
                                    break;
                                } else {
                                    $part2 = basename($part1) . '/' . $part2;
                                    $part1 = dirname($part1);
                                }
                            }
                            
                            // 限制串栏目多路径
                            if ($scode != $data->scode) {
                                $err = true;
                            }
                            
                            // 限制串内容ID及名称多路径
                            if (! ! $data->filename && $part2 != $data->filename) {
                                $err = true;
                            }
                        }
                        
                        // 执行未配置栏目名称但是配置了模型路径的情况路径匹配
                        if (! $iscontent) {
                            preg_match('/^([a-zA-Z0-9\-\/]+)(' . $url_break_char . '([0-9]+))?' . $url_break_char . '([0-9]+)$/i', $path, $matchs);
                            if ($matchs[2] && $model = $this->model->checkModelUrlname($matchs[1])) {
                                // 模型名称_栏目ID_分页
                                // define('CMS_PAGE_CUSTOM', false);
                                $sort = $this->model->getSort($matchs[3]);
                                $_GET['page'] = $matchs[4]; // 分页
                            } elseif (! ! $model = $this->model->checkModelUrlname($matchs[1])) {
                                // 模型名称_栏目ID
                                $sort = $this->model->getSort($matchs[4]);
                            }
                            
                            // 限制串模型和栏目名称多路径，当栏目名称不为空时不允许使用模型路径
                            if ($sort->filename != '') {
                                $err = true;
                            }
                            
                            // 限制串模型多路径
                            if (! ! $sort->urlname && $matchs[1] != $sort->urlname) {
                                $err = true;
                            }
                        }
                    }
                    
                    if ($iscontent) {
                        if (! ! $data && $suffix && ! $err) {
                            echo $this->getContentPage($data);
                        } else {
                            _404('您访问的内容不存在，请核对后重试！');
                        }
                    } else {
                        if (! ! $sort && ! $suffix && ! $err) {
                            if ($sort->type == 1) {
                                echo $this->getAboutPage($sort);
                            } else {
                                echo $this->getListPage($sort);
                            }
                        } else {
                            _404('您访问的页面不存在，请核对后重试！');
                        }
                    }
            }
        } else {
            echo $this->getIndexPage();
        }
    }
}