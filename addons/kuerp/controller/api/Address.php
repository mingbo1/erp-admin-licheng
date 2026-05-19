<?php

namespace addons\kuerp\controller\api;

use think\Db;

class Address extends Base
{
    /**
     * 收货地址列表
     * GET /api/kuerp/address/index
     */
    public function index()
    {
        if (!$this->auth->isLogin()) {
            return json(['code' => 1, 'msg' => '请先登录']);
        }

        $customerId = $this->getCustomerId();
        if (!$customerId) {
            return json(['code' => 0, 'msg' => '成功', 'data' => ['list' => []]]);
        }

        $list = Db::name('kuerp_customers_user')
            ->where('sid', $customerId)
            ->where('status', '1')
            ->order('is_first', 'desc')
            ->order('id', 'desc')
            ->select();

        return json(['code' => 0, 'msg' => '成功', 'data' => ['list' => $list]]);
    }

    /**
     * 新增收货地址
     * POST /api/kuerp/address/add
     */
    public function add()
    {
        if (!$this->auth->isLogin()) {
            return json(['code' => 1, 'msg' => '请先登录']);
        }

        $customerId = $this->getCustomerId();
        if (!$customerId) {
            return json(['code' => 1, 'msg' => '客户信息不存在']);
        }

        $name    = $this->request->post('name', '');
        $mobile  = $this->request->post('mobile', '');
        $address = $this->request->post('address', '');
        $province = $this->request->post('province', '');
        $city    = $this->request->post('city', '');
        $district = $this->request->post('district', '');
        $isDefault = $this->request->post('is_default/d', 0);

        if (!$name || !$mobile || !$address) {
            return json(['code' => 1, 'msg' => '请填写完整信息']);
        }

        // 如果设为默认，先取消其他默认
        if ($isDefault) {
            Db::name('kuerp_customers_user')
                ->where('sid', $customerId)
                ->update(['is_first' => 0]);
        }

        // 构建完整地址
        $fullAddress = $address;
        if ($province || $city || $district) {
            $fullAddress = trim($province . ' ' . $city . ' ' . $district . ' ' . $address);
        }

        $data = [
            'sid'                  => $customerId,
            'first_connect_name'   => $name,
            'first_connect_mobile' => $mobile,
            'connect_address'      => $fullAddress,
            'is_first'             => $isDefault ? 1 : 0,
            'status'               => '1',
            'createtime'           => time(),
            'updatetime'           => time(),
        ];
        $id = Db::name('kuerp_customers_user')->insertGetId($data);

        $addressDetail = Db::name('kuerp_customers_user')->find($id);
        return json(['code' => 0, 'msg' => '新增成功', 'data' => ['address' => $addressDetail]]);
    }

    /**
     * 编辑收货地址
     * POST /api/kuerp/address/edit
     */
    public function edit()
    {
        if (!$this->auth->isLogin()) {
            return json(['code' => 1, 'msg' => '请先登录']);
        }

        $customerId = $this->getCustomerId();
        $id = $this->request->post('id/d', 0);

        if (!$id) {
            return json(['code' => 1, 'msg' => '参数错误']);
        }

        $address = Db::name('kuerp_customers_user')
            ->where('id', $id)
            ->where('sid', $customerId)
            ->find();
        if (!$address) {
            return json(['code' => 1, 'msg' => '地址不存在']);
        }

        $data = ['updatetime' => time()];
        $name    = $this->request->post('name');
        $mobile  = $this->request->post('mobile');
        $province = $this->request->post('province');
        $city    = $this->request->post('city');
        $district = $this->request->post('district');
        $street  = $this->request->post('address');
        $isDefault = $this->request->post('is_default/d', -1);

        if ($name) {
            $data['first_connect_name'] = $name;
        }
        if ($mobile) {
            $data['first_connect_mobile'] = $mobile;
        }
        if ($street || $province !== null || $city !== null || $district !== null) {
            // 从现有 connect_address 中解析省市区（格式: "province city district street"）
            $addrParts = preg_split('/\s+/', $address['connect_address'], 4);
            $p = $province !== null ? $province : ($addrParts[0] ?? '');
            $c = $city !== null ? $city : ($addrParts[1] ?? '');
            $d = $district !== null ? $district : ($addrParts[2] ?? '');
            $a = $street ?: ($addrParts[3] ?? '');
            $data['connect_address'] = trim("$p $c $d $a");
        }

        if ($isDefault == 1) {
            Db::name('kuerp_customers_user')
                ->where('sid', $customerId)
                ->where('id', '<>', $id)
                ->update(['is_first' => 0]);
            $data['is_first'] = 1;
        } elseif ($isDefault === 0) {
            $data['is_first'] = 0;
        }

        Db::name('kuerp_customers_user')->where('id', $id)->update($data);

        $addressDetail = Db::name('kuerp_customers_user')->find($id);
        return json(['code' => 0, 'msg' => '编辑成功', 'data' => ['address' => $addressDetail]]);
    }

    /**
     * 删除收货地址
     * POST /api/kuerp/address/delete
     */
    public function delete()
    {
        if (!$this->auth->isLogin()) {
            return json(['code' => 1, 'msg' => '请先登录']);
        }

        $customerId = $this->getCustomerId();
        $id = $this->request->post('id/d', 0);

        if (!$id) {
            return json(['code' => 1, 'msg' => '参数错误']);
        }

        Db::name('kuerp_customers_user')
            ->where('id', $id)
            ->where('sid', $customerId)
            ->delete();

        return json(['code' => 0, 'msg' => '删除成功']);
    }

    /**
     * 设为默认地址
     * POST /api/kuerp/address/set-default
     */
    public function setDefault()
    {
        if (!$this->auth->isLogin()) {
            return json(['code' => 1, 'msg' => '请先登录']);
        }

        $customerId = $this->getCustomerId();
        $id = $this->request->post('id/d', 0);

        if (!$id) {
            return json(['code' => 1, 'msg' => '参数错误']);
        }

        Db::name('kuerp_customers_user')
            ->where('sid', $customerId)
            ->update(['is_first' => 0]);

        Db::name('kuerp_customers_user')
            ->where('id', $id)
            ->where('sid', $customerId)
            ->update(['is_first' => 1, 'updatetime' => time()]);

        return json(['code' => 0, 'msg' => '设置成功']);
    }
}
