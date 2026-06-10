<?php

namespace app\admin\controller\kuerp;

use app\common\controller\Backend;
use think\Db;

/**
 * 系统配置
 *
 * @icon fa fa-cog
 */
class MiniConfig extends Backend
{
    protected $model = null;

    protected $noNeedRight = ['*'];

    public function _initialize()
    {
        parent::_initialize();
        $setData = \addons\kuerp\library\Goods::getSetData();
        $this->assign('set', $setData['setList']);
        $this->assignconfig('set', $setData['setList']);
        $this->assign('nowmenu', $setData['nowmenu']);

        $this->model = new \app\admin\model\kuerp\MiniConfig;
    }

    /**
     * 查看配置列表
     */
    public function index()
    {
        if ($this->request->isPost()) {
            $params = $this->request->post("row/a");
            if ($params) {
                foreach ($params as $v) {
                    if (isset($v['id']) && $v['id'] && isset($v['value'])) {
                        Db::name('kuerp_mini_config')->where('id', $v['id'])->update([
                            'value'      => $v['value'],
                            'updatetime' => time(),
                        ]);
                    }
                }
                $this->success(__('保存成功'));
            }
            $this->error(__('参数错误'));
        }

        // 自动初始化默认配置项
        $this->initDefaults();

        $list = Db::name('kuerp_mini_config')
            ->field('id,name,value,description')
            ->order('id', 'asc')
            ->select();
        $this->view->assign('list', $list);
        return $this->view->fetch();
    }

    /**
     * 自动初始化系统配置项
     */
    private function initDefaults()
    {
        $defaults = [
            ['name' => 'notice_text', 'value' => '', 'description' => '公告文本'],
            ['name' => 'home_name', 'value' => '', 'description' => '首页名称'],
            ['name' => 'user_agreement', 'value' => '', 'description' => '用户协议'],
            ['name' => 'privacy_policy', 'value' => '', 'description' => '隐私协议'],
            ['name' => 'app_id', 'value' => '', 'description' => 'AppId'],
            ['name' => 'app_secret', 'value' => '', 'description' => 'AppSecret'],
        ];

        foreach ($defaults as $item) {
            $exists = Db::name('kuerp_mini_config')->where('name', $item['name'])->find();
            if (!$exists) {
                $item['createtime'] = time();
                $item['updatetime'] = time();
                Db::name('kuerp_mini_config')->insert($item);
            }
        }

        // 清理已废弃的配置项
        Db::name('kuerp_mini_config')->where('name', 'mini_agreement')->delete();
    }

    /**
     * 编辑配置
     */
    public function edit($ids = '')
    {
        $row = Db::name('kuerp_mini_config')->where('id', $ids)->find();
        if (!$row) {
            $this->error(__('配置不存在'));
        }

        if ($this->request->isPost()) {
            $value = $this->request->post('value');
            Db::name('kuerp_mini_config')->where('id', $ids)->update([
                'value'      => $value,
                'updatetime' => time(),
            ]);
            $this->success(__('保存成功'));
        }

        $this->view->assign('row', $row);
        return $this->view->fetch();
    }
}
