<?php
/**
 * @author xsh
 * @email hnxsh@foxmail.com
 * @date 2021年10月31日
 *
 */
namespace app\admin\controller\content;

use core\basic\Controller;
use app\home\controller\GetPageController;
use core\basic\Config;

class MakeHtmlController extends GetPageController
{

    public function index()
    {
        if (! ! $type = get('type', 'int')) {
            
            // 设置前台模板路径，避免后端调用问题
            if (($tpl_dir = Config::get('tpl_dir')) && array_key_exists('home', $tpl_dir)) {
                $this->assign('tplpath', ROOT_PATH . $tpl_dir['home']);
            } else {
                $this->assign('tplpath', APP_PATH . '/home/view');
            }
            
            // 定义当前操作为生成静态，后期动作判断
            define('MAKEHTML', true);
            
            // 执行生成过程
            switch ($type) {
                case '1':
                    $this->makeIndex();
                    alert('生成首页成功', 1);
                    break;
                case '2':
                    $this->makeIndex();
                    $this->makeSort();
                    $this->makeContont();
                    alert('生成全站成功', 1);
                    break;
                case '3':
                    $this->makeSort();
                    alert('生成栏目成功', 1);
                    break;
                case '4':
                    $this->makeContont();
                    alert('生成内容成功', 1);
                    break;
                default:
                    alert('传递参数错误');
            }
        } else {
            $sort_model = model('admin.content.ContentSort');
            $sort_tree = $sort_model->getSelect();
            $this->assign('sort_select', $this->makeSortSelect($sort_tree));
            $this->assign('sort_select2', $this->makeSortSelect($sort_tree, true));
            $this->display('content/makehtml.html');
        }
    }

    private function makeIndex()
    {
        $content = $this->getIndexPage();
        $path = ROOT_PATH . '/index.html';
        if (! file_put_contents($path, $content)) {
            alert_back('首页生成出错！请检查目录是否有写入权限！');
        }
    }

    private function makeSort()
    {
        $scode = get('scode', 'var');
        if ($scode === '') {
            alert_back('请选择需要生成的栏目！');
        } elseif ($scode === '0') {
            $scodes = $this->model->getScodes('1,2');
        } else {
            $scodes = $this->model->getSubScodes($scode);
        }
        
        $err = '';
        foreach ($scodes as $value) {
            $sort = $this->model->getSort($value);
            if ($sort) {
                if ($sort->type == 1) {
                    $content = $this->getAboutPage($sort);
                    $link = $this->getVar('pagelink');
                    $path = ROOT_PATH . $link;
                    if (! check_dir($path, true)) {
                        $err = '栏目目录' . $path . '生成出错！请检查站点写入权限！';
                        continue;
                    }
                    if (! file_put_contents($path . 'index.html', $content)) {
                        $err = '栏目页面' . $sort->name . '生成失败！请检查目录写入权限！';
                    }
                } else {
                    $content = $this->getListPage($sort);
                    $pagelink = $this->getVar('pagelink');
                    $page = ($this->getVar('pagecount') > 1) ? $this->getVar('pagecount') : 1;
                    for ($i = 1; $i <= $page; $i ++) {
                        $_GET['page'] = $i;
                        $content = $this->getListPage($sort);
                        if ($i > 1) {
                            $path = ROOT_PATH . rtrim($pagelink, '/') . '_' . $i . '/';
                        } else {
                            $path = ROOT_PATH . $pagelink;
                        }
                        if (! check_dir($path, true)) {
                            $err = '栏目目录' . $path . '生成出错！请检查站点写入权限！';
                            continue;
                        }
                        if (! file_put_contents($path . 'index.html', $content)) {
                            $err = '栏目页面' . $sort->name . '生成失败！请检查目录写入权限！';
                        }
                    }
                }
            }
        }
        if ($err) {
            alert_back($err);
        }
    }

    private function makeContont()
    {
        $scode = get('scode', 'var');
        if ($scode === '') {
            alert_back('请选择需要生成的栏目！');
        } elseif ($scode === '0') {
            $scodes = $this->model->getScodes(2);
        } else {
            $scodes = $this->model->getSubScodes($scode);
        }
        $ids = $this->model->getContentIds($scodes);
        
        $err = '';
        foreach ($ids as $value) {
            $data = $this->model->getContent($value);
            if ($data) {
                $link = $this->parser->parserLink($data->type, $data->urlname, 'content', $data->scode, $data->sortfilename, $data->id, $data->filename);
                $path = ROOT_PATH . $link;
                if (! check_dir(dirname($path), true)) {
                    $err = '内容目录' . $path . '生成出错！请检查站点写入权限！';
                    continue;
                }
                $content = $this->getContentPage($data);
                if (! file_put_contents($path, $content)) {
                    $err = '内容页面' . $content->id . '生成失败！请检查目录写入权限！';
                }
            }
        }
        if ($err) {
            alert_back($err);
        }
    }

    // 生成内容栏目下拉选择
    private function makeSortSelect($tree, $ext_about = false)
    {
        $list_html = '';
        foreach ($tree as $value) {
            if (get('scode') != $value->scode) { // 不显示本身，避免出现自身为自己的父节点
                if ($ext_about && $value->type == 1) {
                    $list_html .= "<option value='{$value->scode}' disabled='disabled'>{$this->blank}{$value->name}</option>";
                } else {
                    $list_html .= "<option value='{$value->scode}' >{$this->blank}{$value->name}</option>";
                }
            }
            // 子菜单处理
            if ($value->son) {
                $this->blank .= '　　';
                $list_html .= $this->makeSortSelect($value->son, $ext_about);
            }
        }
        // 循环完后回归位置
        $this->blank = substr($this->blank, 0, - 6);
        return $list_html;
    }
}