<?php

namespace app\admin\model\kuerp;

use think\Model;

class Accounts extends Model
{
    // 表名
    protected $name = 'kuerp_accounts';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';

    // 追加属性
    protected $append = [
        'start_account_time_text',
        'account_type_id_text',
        'is_default_text'
    ];
    

    
    public function getAccountTypeIdList()
    {
        return ['1' => __('Account_type_id 1'), '2' => __('Account_type_id 2')];
    }

    public function getIsDefaultList()
    {
        return ['0' => __('Is_default 0'), '1' => __('Is_default 1')];
    }


    public function getStartAccountTimeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['start_account_time']) ? $data['start_account_time'] : '');
        return is_numeric($value) ? date("Y-m-d H:i:s", $value) : $value;
    }


    public function getAccountTypeIdTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['account_type_id']) ? $data['account_type_id'] : '');
        $list = $this->getAccountTypeIdList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getIsDefaultTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['is_default']) ? $data['is_default'] : '');
        $list = $this->getIsDefaultList();
        return isset($list[$value]) ? $list[$value] : '';
    }

    protected function setStartAccountTimeAttr($value)
    {
        return $value === '' ? null : ($value && !is_numeric($value) ? strtotime($value) : $value);
    }


}
