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
                        { field: 'user_type', title: __('User_type'), width: 90, templet: function (d) {
                            return d.user_type_text || d.user_type || '-';
                        }},
                        { field: 'staff_id', title: __('Staff_id'), width: 160, templet: function (d) {
                            if (d.staffmember && d.staffmember.name) {
                                return (d.staffmember.men_code || '') + ' ' + d.staffmember.name;
                            }
                            return '-';
                        }},
                        { field: 'apptype', title: __('Apptype'), width: 80, templet: function (d) {
                            return d.apptype_text || d.apptype || '-';
                        }},
                        { field: 'createdtime', title: __('Createdtime'), width: 160, templet: function (d) {
                            return d.createdtime ? new Date(d.createdtime * 1000).toLocaleString() : '-';
                        }},
                        { field: 'status', title: __('Status'), width: 80, templet: function (d) {
                            return d.status == 'normal' ? '<span class="label label-success">正常</span>' : '<span class="label label-default">禁用</span>';
                        }},
                        { field: 'id', title: __('Operate'), width: 160, templet: function (d) {
                            return '<a class="layui-btn layui-btn-xs" lay-event="edit">编辑</a><a class="layui-btn layui-btn-xs layui-btn-danger" lay-event="del">删除</a>';
                        }}
                    ]]
                });

                // 头工具栏事件
                table.on('toolbar(table)', function(obj){
                    switch(obj.event){
                        case 'refresh':
                            table.reload('table', []);
                            break;
                        case 'add':
                            Fast.api.open("kuerp/mini_user/add", __('Add_mini_user'), {area:['50%','70%']});
                            break;
                    }
                });

                // 监听行工具事件
                table.on('tool(table)', function(obj){
                    var data = obj.data;
                    if(obj.event === 'edit'){
                        Fast.api.open("kuerp/mini_user/edit/ids/" + data.id, __('Edit'), {area:['50%','70%']});
                    } else if(obj.event === 'del'){
                        var that = this;
                        var top = $(that).offset().top - $(window).scrollTop();
                        var left = $(that).offset().left - $(window).scrollLeft() - 260;
                        if (top + 154 > $(window).height()) {
                            top = top - 154;
                        }
                        if ($(window).width() < 480) {
                            top = left = undefined;
                        }
                        Layer.confirm(
                            __('Are you sure you want to delete this item?'),
                            {icon: 3, title: __('Warning'), offset: [top, left], shadeClose: true},
                            function (index) {
                                $.ajax({
                                    url: 'kuerp/mini_user/del',
                                    type: 'POST',
                                    data: { ids: data.id },
                                    dataType: 'json',
                                    success: function (res) {
                                        if (res.code == 1) {
                                            table.reload('table', []);
                                        }
                                        Layer.close(index);
                                    },
                                    error: function () {
                                        Layer.close(index);
                                    }
                                });
                            }
                        );
                    }
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
                    var userType = $('#user_type').val();
                    if (userType) search.user_type = userType;
                    return search;
                }

                function getOp() {
                    return { nickname: 'LIKE', mobile: 'LIKE', user_type: '=' };
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
