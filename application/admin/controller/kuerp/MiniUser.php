<?php

namespace app\admin\controller\kuerp;

use app\common\controller\Backend;
use think\Db;
use think\Exception;
use think\Session;

/**
 * 小程序用户管理
 *
 * @icon fa fa-users
 */
class MiniUser extends Backend
{
    protected $model = null;

    protected $noNeedRight = ['*'];

    protected $relationSearch = true;

    public function _initialize()
    {
        parent::_initialize();
        $setData = \addons\kuerp\library\Goods::getSetData();
        $this->assign('set', $setData['setList']);
        $this->assignconfig('set', $setData['setList']);
        $this->assign('nowmenu', $setData['nowmenu']);

        $this->model = new \app\admin\model\kuerp\MiniUser;
        $this->view->assign("statusList", $this->model->getStatusList());
        $this->view->assign("apptypeList", $this->model->getApptypeList());
        $this->view->assign("userTypeList", $this->model->getUserTypeList());
    }

    /**
     * 查看列表
     */
    public function index()
    {
        if ($this->request->isAjax()) {
            list($where, $sort, $order, $offset, $limit) = $this->buildparams();
            $list = $this->model
                ->with(['staffmember'])
                ->where($where)
                ->order($sort, $order)
                ->paginate($limit);

            foreach ($list as $row) {
                if($row->staffmember){
                $row->getRelation('staffmember')->visible(['men_code', 'name']);

                }
            }

            $result = ["total" => $list->total(), "rows" => $list->items()];
            return json($result);
        }

        $staffList = Db::name('kuerp_staff_member')
            ->where(['status' => '1'])
            ->field('id, men_code, name')
            ->select();
        $this->view->assign("staffList", $staffList);
        $this->assignconfig("staffList", $staffList);

        return $this->view->fetch();
    }

    /**
     * 添加用户
     */
    public function add()
    {
        if ($this->request->isPost()) {
            $params = $this->request->post("row/a");
            if ($params) {
                $mobile   = trim($params['mobile'] ?? '');
                $password = trim($params['password'] ?? '');
                $userType = $params['user_type'] ?? 'sales';
                $staffId  = (int)($params['staff_id'] ?? 0);
                $nickname = trim($params['nickname'] ?? '') ?: $mobile;

                if (!$mobile || strlen($mobile) < 11) {
                    $this->error('请输入正确的手机号');
                }
                if (strlen($password) < 6) {
                    $this->error('密码至少6位');
                }

                // 检查手机号是否已注册 fa_user
                $existUser = \app\common\model\User::where('mobile', $mobile)->find();
                if ($existUser) {
                    // fa_user 已存在，检查是否已有关联 mini_user
                    $existMini = $this->model->where('user_id', $existUser->id)->find();
                    if ($existMini) {
                        $this->error('该手机号已绑定小程序用户');
                    }
                    $userId = $existUser->id;
                } else {
                    // 创建 fa_user
                    $salt   = \fast\Random::alnum();
                    $pass   = \app\common\library\Auth::instance()->getEncryptPassword($password, $salt);
                    $userId = Db::name('user')->insertGetId([
                        'group_id'   => 1,
                        'username'   => $mobile,
                        'nickname'   => $nickname,
                        'mobile'     => $mobile,
                        'password'   => $pass,
                        'salt'       => $salt,
                        'status'     => 'normal',
                        'jointime'   => time(),
                        'createtime' => time(),
                        'updatetime' => time(),
                    ]);
                    if (!$userId) {
                        $this->error('创建用户失败');
                    }
                }

                // 检查是否已有关联客户
                $customerId = Db::name('kuerp_customers')->where('customer_mobile', $mobile)->value('id');
                if (!$customerId) {
                    $customerCode = \addons\kuerp\library\Goods::getCodes('', 1);
                    $customerId   = Db::name('kuerp_customers')->insertGetId([
                        'customer_name'   => $nickname,
                        'customer_code'   => $customerCode,
                        'customer_mobile' => $mobile,
                        'customerlevel_id'=> 1,
                        'customertype_id' =>1,
                        'status'          => 1,
                        'createtime'      => time(),
                        'updatetime'      => time(),
                    ]);
                    if (!$customerId) {
                        $this->error('创建客户失败');
                    }
                }

                // 创建 mini_user
                $result = $this->model->save([
                    'user_id'      => $userId,
                    'customer_id'  => $customerId,
                    'mobile'       => $mobile,
                    'apptype'      => 'mobile',
                    'user_type'    => $userType,
                    'staff_id'     => $staffId,
                    'openid'       => 'mobile_' . $userId,
                    'nickname'     => $nickname,
                    'status'       => $params['status'] ?? 'normal',
                    'createdtime'  => time(),
                    'updatetime'   => time(),
                ]);

                if ($result !== false) {
                    $this->success();
                } else {
                    $this->error(__('No rows were inserted'));
                }
            }
            $this->error(__('Parameter %s can not be empty', ''));
        }

        $staffList = Db::name('kuerp_staff_member')
            ->where(['status' => '1'])
            ->field('id, men_code, name')
            ->select();
        $this->view->assign("staffList", $staffList);
        $this->view->assign("row", []);

        return $this->view->fetch();
    }

    /**
     * 编辑用户
     */
    public function edit($ids = null)
    {
        $row = $this->model->get($ids);
        if (!$row) {
            $this->error(__('No Results were found'));
        }

        if ($this->request->isPost()) {
            $params = $this->request->post("row/a");
            if ($params) {
                $update = [];
                if (isset($params['status'])) {
                    $update['status'] = $params['status'];
                }
                if (isset($params['user_type'])) {
                    $update['user_type'] = $params['user_type'];
                }
                if (isset($params['staff_id'])) {
                    $update['staff_id'] = (int)$params['staff_id'];
                }
                if (isset($params['nickname'])) {
                    $update['nickname'] = trim($params['nickname']);
                }
                if (isset($params['mobile']) && trim($params['mobile'])) {
                    $update['mobile'] = trim($params['mobile']);
                }
                $update['updatetime'] = time();

                // 如果传入了新密码，同步更新 fa_user 的密码
                $newPassword = trim($params['password'] ?? '');
                if ($newPassword && strlen($newPassword) >= 6) {
                    $salt      = \fast\Random::alnum();
                    $encrypted = \app\common\library\Auth::instance()->getEncryptPassword($newPassword, $salt);
                    Db::name('user')->where('id', $row['user_id'])->update([
                        'password'   => $encrypted,
                        'salt'       => $salt,
                        'updatetime' => time(),
                    ]);
                }

                $result = $this->model->where('id', $ids)->update($update);

                // 如果手机号变更，同步更新 fa_user
                if (!empty($update['mobile']) && $update['mobile'] !== $row['mobile']) {
                    Db::name('user')->where('id', $row['user_id'])->update([
                        'mobile'     => $update['mobile'],
                        'username'   => $update['mobile'],
                        'updatetime' => time(),
                    ]);
                }

                if ($result !== false) {
                    $this->success();
                } else {
                    $this->error(__('No rows were updated'));
                }
            }
            $this->error(__('Parameter %s can not be empty', ''));
        }

        $staffList = Db::name('kuerp_staff_member')
            ->where(['status' => '1'])
            ->field('id, men_code, name')
            ->select();
        $this->view->assign("staffList", $staffList);
        $this->view->assign("row", $row);

        return $this->view->fetch();
    }

    /**
     * 删除
     */
    public function del($ids = "")
    {
        if (!$this->request->isPost()) {
            $this->error(__("Invalid parameters"));
        }
        $ids = $ids ? $ids : $this->request->post("ids");
        if ($ids) {
            $pk   = $this->model->getPk();
            $list = $this->model->where($pk, 'in', $ids)->select();

            $count = 0;
            Db::startTrans();
            try {
                foreach ($list as $v) {
                    $count += $v->delete();
                    // 同步禁用/删除关联的 fa_user
                    if ($v['user_id']) {
                        Db::name('user')->where('id', $v['user_id'])->update([
                            'status'     => 'hidden',
                            'updatetime' => time(),
                        ]);
                    }
                }
                Db::commit();
            } catch (Exception $e) {
                Db::rollback();
                $this->error($e->getMessage());
            }

            if ($count) {
                $this->success();
            } else {
                $this->error(__('No rows were deleted'));
            }
        }
        $this->error(__('Parameter %s can not be empty', 'ids'));
    }
}
