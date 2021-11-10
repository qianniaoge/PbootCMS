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

    public function index($type = null, $data = null)
    {
        if ($type || ! ! $type = get('type', 'int')) {
            
            // 检查开关状态
            if ($this->config('url_rule_type') != 4) {
                json(0, '请先开启伪静态功能！');
            }
            
            // 设置基础信息
            $this->setinfo();
            
            // 执行生成过程
            switch ($type) {
                case '1':
                    $this->makeIndex();
                    json(1, '生成首页成功');
                    break;
                case '2':
                    $this->makeIndex();
                    $this->makeSort(0);
                    $this->makeContont($data);
                    json(1, '生成全站成功');
                    break;
                case '3':
                    $this->makeSort($data);
                    json(1, '生成栏目成功');
                    break;
                case '4':
                    $this->makeContont($data);
                    json(1, '生成内容成功');
                    break;
                case '5':
                    $this->makeContont($data);
                    json(1, '生成内容成功');
                    break;
                default:
                    json(0, '传递参数错误');
            }
        } else {
            $sort_model = model('admin.content.ContentSort');
            $sort_tree = $sort_model->getSelect();
            $this->assign('sort_select', $this->makeSortSelect($sort_tree));
            $this->assign('sort_select2', $this->makeSortSelect($sort_tree, true));
            $this->display('content/makehtml.html');
        }
    }

    // 生成首页
    private function makeIndex()
    {
        $content = $this->getIndexPage();
        $path = ROOT_PATH . '/index.html';
        if (! file_put_contents($path, $content)) {
            json(0, '首页生成出错！请检查目录是否有写入权限！');
        }
    }

    // 生成栏目
    private function makeSort($scode = null)
    {
        $scode = $scode ?: get('scode', 'var');
        if ($scode === '') {
            json(0, '请选择需要生成的栏目！');
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
            json(0, $err);
        }
    }

    // 获取内容id集
    public function getContentIds($ajax = true)
    {
        // 检查开关状态
        if ($this->config('url_rule_type') != 4) {
            json(0, '请先开启伪静态功能！');
        }
        
        $scode = get('scode', 'var') ?: "0";
        $ids = array();
        if ($scode === '') {
            json(0, '请选择需要生成的栏目！');
        } elseif ($scode === '0') {
            $scodes = $this->model->getScodes(2);
        } else {
            $scodes = $this->model->getSubScodes($scode);
        }
        
        $where = "";
        if ((! ! $date = get('date', 'var')) && get('type', 'int') == 5) {
            $where = "substr(date,1,10)='" . $date . "'";
        }
        
        $ids = $this->model->getContentIds($scodes, $where);
        if ($ajax) {
            json(1, $ids);
        } else {
            return $ids;
        }
    }

    // 生成内容页
    private function makeContont($ids = null)
    {
        $ids = $ids ?: get('ids', 'var') ?: $this->getContentIds(false);
        if (! is_array($ids)) {
            $ids = explode(',', $ids);
        }
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
                    $err = '内容页面' . $data->id . '生成失败！请检查目录写入权限！';
                    continue;
                }
            }
        }
        if ($err) {
            json(0, $err);
        }
    }

    // 生成内容栏目下拉选择
    private function makeSortSelect($tree, $ext_about = false)
    {
        $list_html = '';
        foreach ($tree as $value) {
            if (get('scode') != $value->scode) { // 不显示本身，避免出现自身为自己的父节点
                if (($ext_about && $value->type == 1) || $value->outlink != '') {
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

    // 设置公共信息
    private function setinfo()
    {
        // 设置前台模板路径，避免后端调用问题
        if (($tpl_dir = Config::get('tpl_dir')) && array_key_exists('home', $tpl_dir)) {
            $this->assign('tplpath', ROOT_PATH . $tpl_dir['home']);
        } else {
            $this->assign('tplpath', APP_PATH . '/home/view');
        }
        // 定义当前操作为生成静态，后期动作判断
        define('MAKEHTML', true);
    }
}