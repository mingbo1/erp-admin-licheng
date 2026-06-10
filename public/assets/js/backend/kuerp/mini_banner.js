define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'kuerp/mini_banner/index' + location.search,
                    add_url: 'kuerp/mini_banner/add',
                    edit_url: 'kuerp/mini_banner/edit',
                    del_url: 'kuerp/mini_banner/del',
                    multi_url: 'kuerp/mini_banner/multi',
                }
            });

            var table = $("#table");

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'id',
                columns: [
                    [
                        {checkbox: true},
                        {field: 'title', title: __('Title'), operate: 'LIKE'},
                        {field: 'image', title: __('Image'), formatter: function (value) {
                            return '<img src="' + value + '" style="height:30px;">';
                        }},
                        {field: 'sort', title: __('Sort'), sortable: true},
                        {field: 'status', title: __('Status'), searchList: {"normal": __('启用'), "hidden": __('禁用')}, formatter: Table.api.formatter.status},
                        {field: 'operate', title: __('Operate'), table: table, events: Table.api.events.operate, formatter: Table.api.formatter.operate}
                    ]
                ]
            });

            // 为表格绑定事件
            Table.api.bindevent(table);

            // 搜索事件
            $('#search').on('click', function () {
                var search = {};
                var title = $('#title').val();
                if (title) search.title = title;
                var status = $('#status').val();
                if (status) search.status = status;
                table.bootstrapTable('refresh', {
                    url: $.fn.bootstrapTable.defaults.extend.index_url,
                    query: {
                        filter: JSON.stringify(search),
                        op: JSON.stringify({title: 'LIKE'})
                    }
                });
            });
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
