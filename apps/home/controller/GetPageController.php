<?php
/**
 * @author xsh
 * @email hnxsh@foxmail.com
 * @date 2021年10月31日
 *
 */
namespace app\home\controller;

use core\basic\Controller;
use app\home\model\ParserModel;
use core\basic\Config;
use core\basic\Url;

class GetPageController extends Controller
{

    protected $parser;

    protected $model;

    protected $htmldir;

    public function __construct()
    {
        $this->parser = new ParserController();
        $this->model = new ParserModel();
        $this->htmldir = $this->config('tpl_html_dir') ? $this->config('tpl_html_dir') . '/' : '';
    }

    // 首页
    protected function getIndexPage()
    {
        $link = SITE_INDEX_DIR . '/';
        $this->assign('pagelink', $link);
        $content = parent::parser($this->htmldir . 'index.html'); // 框架标签解析
        $content = $this->parser->parserBefore($content); // CMS公共标签前置解析
        $content = str_replace('{pboot:pagetitle}', $this->config('index_title') ?: '{pboot:sitetitle}-{pboot:sitesubtitle}', $content);
        $content = str_replace('{pboot:pagelink}', $link, $content);
        $content = $this->parser->parserPositionLabel($content, - 1, '首页', SITE_INDEX_DIR . '/'); // CMS当前位置标签解析
        $content = $this->parser->parserSpecialPageSortLabel($content, 0, '', SITE_INDEX_DIR . '/'); // 解析分类标签
        $content = $this->parser->parserAfter($content); // CMS公共标签后置解析
        $this->cache($content, false);
        return $content;
    }

    // 列表
    protected function getListPage($sort)
    {
        
        // 调用栏目语言与当前语言不一致时，自动切换语言
        if ($sort->acode != get_lg() && Config::get('lgautosw') !== '0') {
            cookie('lg', $sort->acode);
        }
        if ($sort->listtpl) {
            
            $this->checkPageLevel($sort->gcode, $sort->gtype, $sort->gnote); // 检查页面权限
            $this->assign('listpage', true);
            $link = $this->parser->parserLink($sort->type, $sort->urlname, 'list', $sort->scode, $sort->filename, '', '');
            $this->assign('pagelink', $link); // 注入当前页面链接
            
            $content = parent::parser($this->htmldir . $sort->listtpl); // 框架标签解析
            $content = $this->parser->parserBefore($content); // CMS公共标签前置解析
            $pagetitle = $sort->title ? "{sort:title}" : "{sort:name}"; // 页面标题
            $content = str_replace('{pboot:pagetitle}', $this->config('list_title') ?: ($pagetitle . '-{pboot:sitetitle}-{pboot:sitesubtitle}'), $content);
            $content = str_replace('{pboot:pagekeywords}', '{sort:keywords}', $content);
            $content = str_replace('{pboot:pagedescription}', '{sort:description}', $content);
            $content = str_replace('{pboot:pagelink}', $link, $content);
            $content = $this->parser->parserPositionLabel($content, $sort->scode); // CMS当前位置标签解析
            $content = $this->parser->parserSortLabel($content, $sort); // CMS分类信息标签解析
            $content = $this->parser->parserListLabel($content, $sort->scode); // CMS分类列表标签解析
            $content = $this->parser->parserAfter($content); // CMS公共标签后置解析
        } else {
            error('请到后台设置分类栏目列表页模板！');
        }
        $this->cache($content, false);
        return $content;
    }

    // 详情页
    protected function getContentPage($data)
    {
        // 调用内容语言与当前语言不一致时，自动切换语言
        if ($data->acode != get_lg() && Config::get('lgautosw') !== '0') {
            cookie('lg', $data->acode);
        }
        
        // 读取模板
        if (! ! $sort = $this->model->getSort($data->scode)) {
            $link = $this->parser->parserLink($data->type, $data->urlname, 'content', $data->scode, $data->sortfilename, $data->id, $data->filename);
            $this->assign('pagelink', $link); // 注入当前页面链接
            if ($sort->contenttpl) {
                $this->checkPageLevel($sort->gcode, $sort->gtype, $sort->gnote); // 检查栏目权限
                $this->checkPageLevel($data->gcode, $data->gtype, $data->gnote); // 检查内容权限
                $content = parent::parser($this->htmldir . $sort->contenttpl); // 框架标签解析
                $content = $this->parser->parserBefore($content); // CMS公共标签前置解析
                $content = str_replace('{pboot:pagetitle}', $this->config('content_title') ?: '{content:title}-{sort:name}-{pboot:sitetitle}-{pboot:sitesubtitle}', $content);
                $content = str_replace('{pboot:pagekeywords}', '{content:keywords}', $content);
                $content = str_replace('{pboot:pagedescription}', '{content:description}', $content);
                $content = str_replace('{pboot:pagelink}', $link, $content);
                $content = $this->parser->parserPositionLabel($content, $sort->scode); // CMS当前位置标签解析
                $content = $this->parser->parserSortLabel($content, $sort); // CMS分类信息标签解析
                $content = $this->parser->parserCurrentContentLabel($content, $sort, $data); // CMS内容标签解析
                $content = $this->parser->parserCommentLabel($content); // 文章评论
                $content = $this->parser->parserAfter($content); // CMS公共标签后置解析
            } else {
                error('请到后台设置分类栏目内容页模板！');
            }
        } else {
            _404('您访问内容的分类已经不存在，请核对后再试！');
        }
        $this->cache($content, false);
        return $content;
    }

    // 单页
    protected function getAboutPage($sort)
    {
        // 调用栏目语言与当前语言不一致时，自动切换语言
        if ($sort->acode != get_lg() && Config::get('lgautosw') !== '0') {
            cookie('lg', $sort->acode);
        }
        
        // 读取数据
        if (! $data = $this->model->getAbout($sort->scode)) {
            _404('您访问的内容不存在，请核对后重试！');
        }
        
        if ($sort->contenttpl) {
            $link = $this->parser->parserLink($sort->type, $sort->urlname, 'list', $sort->scode, $sort->filename, '', '');
            $this->assign('pagelink', $link); // 注入当前页面链接
            $this->checkPageLevel($sort->gcode, $sort->gtype, $sort->gnote);
            $content = parent::parser($this->htmldir . $sort->contenttpl); // 框架标签解析
            $content = $this->parser->parserBefore($content); // CMS公共标签前置解析
            $pagetitle = $sort->title ? "{sort:title}" : "{content:title}"; // 页面标题
            $content = str_replace('{pboot:pagetitle}', $this->config('about_title') ?: ($pagetitle . '-{pboot:sitetitle}-{pboot:sitesubtitle}'), $content);
            $content = str_replace('{pboot:pagekeywords}', '{content:keywords}', $content);
            $content = str_replace('{pboot:pagedescription}', '{content:description}', $content);
            $content = str_replace('{pboot:pagelink}', $link, $content);
            $content = $this->parser->parserPositionLabel($content, $sort->scode); // CMS当前位置标签解析
            $content = $this->parser->parserSortLabel($content, $sort); // CMS分类信息标签解析
            $content = $this->parser->parserCurrentContentLabel($content, $sort, $data); // CMS内容标签解析
            $content = $this->parser->parserCommentLabel($content); // 文章评论
            $content = $this->parser->parserAfter($content); // CMS公共标签后置解析
        } else {
            error('请到后台设置分类栏目内容页模板！');
        }
        
        $this->cache($content, false);
        return $content;
    }

    // 检查页面权限
    protected function checkPageLevel($gcode, $gtype, $gnote)
    {
        if ($gcode) {
            $deny = false;
            $gtype = $gtype ?: 4;
            switch ($gtype) {
                case 1:
                    if ($gcode <= session('pboot_gcode')) {
                        $deny = true;
                    }
                    break;
                case 2:
                    if ($gcode < session('pboot_gcode')) {
                        $deny = true;
                    }
                    break;
                case 3:
                    if ($gcode != session('pboot_gcode')) {
                        $deny = true;
                    }
                    break;
                case 4:
                    if ($gcode > session('pboot_gcode')) {
                        $deny = true;
                    }
                    break;
                case 5:
                    if ($gcode >= session('pboot_gcode')) {
                        $deny = true;
                    }
                    break;
            }
            if ($deny) {
                $gnote = $gnote ?: '您的权限不足，无法浏览本页面！';
                if (session('pboot_uid')) { // 已经登录
                    error($gnote);
                } else {
                    if ($this->config('login_no_wait')) {
                        location(Url::home('member/login', null, "backurl=" . urlencode(get_current_url())));
                    } else {
                        error($gnote, Url::home('member/login', null, "backurl=" . urlencode(get_current_url())));
                    }
                }
            }
        }
    }
}