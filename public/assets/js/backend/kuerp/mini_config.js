define(['jquery', 'bootstrap', 'backend', 'table', 'form', '/assets/addons/kuerp/layui/layui.js'], function ($, undefined, Backend, Table, Form, Layui) {

    var Controller = {
        index: function () {
            layui.use(['table', 'form'], function () {
                var table = layui.table;

                table.render({
                    elem: '#table',
                    url: 'kuerp/mini_config/index',
                    parseData: function (res) {
                        return { "code": 0, "msg": '', "count": res.total, "data": res.rows };
                    },
                    toolbar: '#toolbar',
                    page: false,
                    cellMinWidth: 100,
                    cols: [[
                        { field: 'name', title: __('Name'), width: 200 },
                        { field: 'value', title: __('Value'), width: 300 },
                        { field: 'description', title: __('Description'), width: 300 },
                        { field: 'id', title: __('Operate'), width: 100, templet: function (d) {
                            return '<a href="kuerp/mini_config/edit/ids/' + d.id + '" class="layui-btn layui-btn-xs">编辑</a>';
                        }}
                    ]]
                });
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
