<?php

namespace app\admin\model\kuerp;

use think\Model;


class StaffMember extends Model
{

    // 表名
    protected $name = 'kuerp_staff_member';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    protected $deleteTime = false;

    // 追加属性
    protected $append = [
        'status_text',
        'type_text'
    ];



    public function getStatusList()
    {
        return ['0' => __('Status 0'), '1' => __('Status 1')];
    }


    public function getStatusTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['status']) ? $data['status'] : '');
        $list = $this->getStatusList();
        return isset($list[$value]) ? $list[$value] : '';
    }

    public function getTypeList()
    {
        return [
            'sales'          => __('Type sales'),
            'plan'           => __('Type plan'),
            'warehouse'      => __('Type warehouse'),
            'finance'        => __('Type finance'),
            'sales_director' => __('Type sales_director'),
            'admin'          => __('Type admin'),
        ];
    }

    public function getTypeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['type']) ? $data['type'] : '');
        $list = $this->getTypeList();
        return isset($list[$value]) ? $list[$value] : '';
    }

    /**
     * 关联管理员
     */
    public function admin()
    {
        return $this->belongsTo('app\admin\model\Admin', 'admin_id', 'id')
            ->bind(['admin_nickname' => 'nickname']);
    }

}
