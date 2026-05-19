define(['jquery', 'bootstrap', 'backend', 'table', 'form', '/assets/addons/kuerp/layui/layui.js'], function ($, undefined, Backend, Table, Form, Layui) {

    var Controller = {
        index: function () {
            layui.use(['table', 'form'], function () {
                var table = layui.table;

                table.render({
                    elem: '#table',
                    url: 'kuerp/mini_banner/index',
                    parseData: function (res) {
                        return { "code": 0, "msg": '', "count": res.total, "data": res.rows };
                    },
                    toolbar: '#toolbar',
                    page: true,
                    cellMinWidth: 100,
                    cols: [[
                        { checkbox: true },
                        { field: 'title', title: __('Title'), width: 150 },
                        { field: 'image', title: __('Image'), width: 200, templet: function (d) {
                            return '<img src="' + d.image + '" style="height:30px;">';
                        }},
                        { field: 'sort', title: __('Sort'), width: 80, sort: true },
                        { field: 'status', title: __('Status'), width: 80, templet: function (d) {
                            return d.status == 'normal' ? '<span class="label label-success">启用</span>' : '<span class="label label-default">禁用</span>';
                        }},
                        { field: 'id', title: __('Operate'), width: 200, templet: function (d) {
                            var url = 'kuerp/mini_banner';
                            return '<a href="' + url + '/edit/ids/' + d.id + '" class="layui-btn layui-btn-xs">编辑</a>'
                                + '<a href="javascript:" class="layui-btn layui-btn-xs layui-btn-danger" onclick="if(confirm(\'确认删除?\')){ $.ajax({type:\'post\',url:\'' + url + '/del/ids/' + d.id + '\',success:function(){location.reload()}}) }">删除</a>';
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
                    var title = $('#title').val();
                    if (title) search.title = title;
                    var status = $('#status').val();
                    if (status) search.status = status;
                    return search;
                }

                function getOp() {
                    return { title: 'LIKE' };
                }
            });
            Controller.api.bindevent();
        },

        add: function () {
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
