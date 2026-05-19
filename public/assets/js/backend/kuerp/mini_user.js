define(['jquery', 'bootstrap', 'backend', 'table', 'form', '/assets/addons/kuerp/layui/layui.js'], function ($, undefined, Backend, Table, Form, Layui) {

    var Controller = {
        index: function () {
            layui.use(['table', 'form'], function () {
                var table = layui.table;

                table.render({
                    elem: '#table',
                    url: 'kuerp/mini_user/index',
                    parseData: function (res) {
                        return { "code": 0, "msg": '', "count": res.total, "data": res.rows };
                    },
                    toolbar: '#toolbar',
                    page: true,
                    cellMinWidth: 100,
                    cols: [[
                        { checkbox: true },
                        { field: 'id', title: 'ID', width: 60 },
                        { field: 'nickname', title: __('Nickname'), width: 120 },
                        { field: 'mobile', title: __('Mobile'), width: 120 },
                        { field: 'apptype', title: __('Apptype'), width: 80, templet: function (d) {
                            return d.apptype_text || d.apptype;
                        }},
                        { field: 'createdtime', title: __('Createdtime'), width: 160, templet: function (d) {
                            return d.createdtime ? new Date(d.createdtime * 1000).toLocaleString() : '-';
                        }},
                        { field: 'customer_id', title: __('Customer_id'), width: 100 },
                        { field: 'status', title: __('Status'), width: 80, templet: function (d) {
                            return d.status == 'normal' ? '<span class="label label-success">正常</span>' : '<span class="label label-default">禁用</span>';
                        }},
                        { field: 'id', title: __('Operate'), width: 100, templet: function (d) {
                            var url = 'kuerp/mini_user';
                            return '<a href="' + url + '/edit/ids/' + d.id + '" class="layui-btn layui-btn-xs">编辑</a>';
                        }}
                    ]]
                });

                $('#search').on('click', function () {
                    table.reload('table', {
                        where: {
                            filter: JSON.stringify(getSearch()),
                            op: JSON.stringify(getOp())
                        },
                        page: { curr: 1 }
                    });
                });

                function getSearch() {
                    var search = {};
                    var nickname = $('#nickname').val();
                    if (nickname) search.nickname = nickname;
                    var mobile = $('#mobile').val();
                    if (mobile) search.mobile = mobile;
                    return search;
                }

                function getOp() {
                    return { nickname: 'LIKE', mobile: 'LIKE' };
                }
            });
            Controller.api.bindevent();
        },

        edit: function () {
            Controller.api.bindevent();
        },
        api: {
            bindevent: function () {
                Form.api.bindevent($("form[role=form]"), function (data, ret) {
                    parent.location.reload();
                });
            }
        }
    };
    return Controller;
});
