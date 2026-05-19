define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            $(".btn-add").data("area", ["48%","75%"]);
            $(".btn-edit").data("area", ["48%","75%"]);
            Table.api.init({
                extend: {
                    index_url: 'kuerp/suppliers/index' + location.search,
                    add_url: 'kuerp/suppliers/add',
                    edit_url: 'kuerp/suppliers/edit',
                    del_url: 'kuerp/suppliers/del',
                    multi_url: 'kuerp/suppliers/multi',
                    import_url: 'kuerp/suppliers/import',
                    table: 'suppliers',
                }
            });

            var table = $("#table");

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'id',
                showToggle: false,//列表显示样式切换
                showColumns: false,//列表显示内容
                columns: [
                    [
                        {checkbox: true},
                        {field: 'id', title: __('Id'),visible: false,operate:false},
                        {field: 'supplyertype.supplier_type_name', title: __('Supplyertype_id'), operate: 'LIKE'},
                        {field: 'supplyer_code', title: __('Supplyer_code'), operate: 'LIKE'},
                        {field: 'supplyer_name', title: __('Supplyer_name'), operate: 'LIKE'},
                        {field: 'need_give_account', title: __('Need_give_account'), operate: 'LIKE'},
                        {field: 'first_connect_name', title: __('First_connect_name'), operate: 'LIKE'},
                        {field: 'first_connect_mobile', title: __('First_connect_mobile'), operate: 'LIKE'},
                        {field: 'first_connect_phone', title: __('First_connect_phone'), operate: 'LIKE'},
                        {field: 'qq_wx_email', title: __('Qq_wx_email'), operate: 'LIKE'},
                        {field: 'connect_address', title: __('Connect_address'), operate: 'LIKE'},
                        {field: 'note', title: __('Note'), operate: 'LIKE'},
                        {field: 'status', title: __('Status'), searchList: {"0":__('Status 0'),"1":__('Status 1')}, formatter: Table.api.formatter.status},
                        {field: 'operate', title: __('Operate'), table: table, events: Table.api.events.operate, formatter: Table.api.formatter.operate}
                    ]
                ]
            });
            table.on('post-body.bs.table',function(){
                $(".btn-editone").data("area",["48%","75%"]);
            });
            // 为表格绑定事件
            Table.api.bindevent(table);
        },
        recyclebin: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    'dragsort_url': ''
                }
            });

            var table = $("#table");

            // 初始化表格
            table.bootstrapTable({
                url: 'kuerp/suppliers/recyclebin' + location.search,
                pk: 'id',
                sortName: 'id',
                columns: [
                    [
                        {checkbox: true},
                        {field: 'id', title: __('Id')},
                        {
                            field: 'deletetime',
                            title: __('Deletetime'),
                            operate: 'RANGE',
                            addclass: 'datetimerange',
                            formatter: Table.api.formatter.datetime
                        },
                        {
                            field: 'operate',
                            width: '130px',
                            title: __('Operate'),
                            table: table,
                            events: Table.api.events.operate,
                            buttons: [
                                {
                                    name: 'Restore',
                                    text: __('Restore'),
                                    classname: 'btn btn-xs btn-info btn-ajax btn-restoreit',
                                    icon: 'fa fa-rotate-left',
                                    url: 'suppliers/restore',
                                    refresh: true
                                },
                                {
                                    name: 'Destroy',
                                    text: __('Destroy'),
                                    classname: 'btn btn-xs btn-danger btn-ajax btn-destroyit',
                                    icon: 'fa fa-times',
                                    url: 'suppliers/destroy',
                                    refresh: true
                                }
                            ],
                            formatter: Table.api.formatter.operate
                        }
                    ]
                ]
            });

            // 为表格绑定事件
            Table.api.bindevent(table);
        },
        add: function () {
            Controller.api.bindevent();
        },
        edit: function () {
            Controller.api.bindevent();
        },
        api: {
            bindevent: function () {
                Form.api.bindevent($("form[role=form]"));
                $(document).on("click", ".del", function(){
                    $(this).parents('tr').remove();
                });
                $(document).on("click", ".add", function(){
                    var obj = $(this).parents('tr');
                    var html = '<tr>\n' +
                        '                <td><div class="btn"><span class="add"><i class="fa fa-plus"></i></span><span class="del"><i class="fa fa-trash"></i></span></div></td>\n' +
                        '                <td><div><input type="text" name="user[name][]" data-rule="required"><input type="hidden" name="user[id][]" value=""></div></td>\n' +
                        '                <td><div><input type="text" name="user[mobile][]"></div></td>\n' +
                        '                <td><div><input type="text" name="user[phone][]"></div></td>\n' +
                        '                <td><div><input type="text" name="user[qqnum][]"></div></td>\n' +
                        '                <td><div><input type="text" name="user[address][]"></div></td>\n' +
                        '                <td><div><select name="user[first][]"><option value="0">否</option><option value="1">是</option></select></div></td>\n' +
                        '            </tr>';
                    $(obj).after(html);
                });
            }
        }
    };
    return Controller;
});