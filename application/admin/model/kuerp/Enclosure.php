<?php

namespace app\admin\model\kuerp;

use think\Model;


class Enclosure extends Model
{

    // 表名
    protected $name = 'kuerp_enclosure';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    protected $deleteTime = false;

    // 追加属性
    protected $append = [

    ];

    // 可在线预览的图片后缀（其余类型按文件图标 + 下载链接展示）
    protected static $imageSuffix = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'ico'];

    /**
     * 补充原始单据的文件展示信息（后缀 / 是否图片 / 访问地址）
     * 采购(kueerp/enclosure)、销售(kueerp/enclosuresale)共用同一张表与同一模型。
     * @param \think\Model $row 单条记录（会被就地修改）
     * @return \think\Model
     */
    public static function fillFileInfo($row)
    {
        $path = $row->getData('image');
        $suffix = $path ? strtolower(pathinfo($path, PATHINFO_EXTENSION)) : '';
        $row->file_suffix = $suffix;
        $row->is_image = in_array($suffix, self::$imageSuffix) ? 1 : 0;
        $row->url = $path ? cdnurl($path, true) : '';
        $row->image = $row->url;
        if (empty($row->name)) {
            // 历史数据没有文件名称时用文件名兜底，保证列表与检索有内容可看
            $row->name = $path ? pathinfo($path, PATHINFO_FILENAME) : '';
        }
        return $row;
    }


    







}
