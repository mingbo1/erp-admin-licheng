define(['jquery', 'bootstrap', 'backend', 'table', 'form', '/assets/addons/kuerp/layui/layui.js'], function ($, undefined, Backend, Table, Form, Layui) {

    var Controller = {
        index: function () {

            layui.use(['table','upload','form'], function(){
                var table = layui.table;
                upload = layui.upload;

                table.render({
                    elem: '#table'
                    ,url:'kuerp/checkout/index'
                    ,parseData: function(res){ //res 即为原始返回的数据
                        return {
                            "code": 0, //解析接口状态
                            "msg": '', //解析提示文本
                            "count": res.total, //解析数据长度
                            "data": res.rows //解析数据列表
                         };
                    }
                    ,toolbar: '#thisToolbar' //开启头部工具栏，并为其绑定左侧模板
                    ,page: true
                    ,cellMinWidth: 80 //全局定义常规单元格的最小宽度，layui 2.2.1 新增
                    ,cols: [[
                        {checkbox: true},
                        {field: 'checkdate', title: __('结账日期')},
                        {field: 'createtime', title: __('操作时间')},
                        {field: 'preparation_id', title: __('操作员'),templet: function (d) {
                                return d.preparation.nickname;
                            }},
                    ]]
                });

                //头工具栏事件
                table.on('toolbar(table)', function(obj){
                    var checkStatus = table.checkStatus(obj.config.id);
                    switch(obj.event){
                        case 'refresh':
                            table.reload('table', []);
                            break;
                        case 'add':
                            Fast.api.open("kuerp/expend_type/add", "添加", {
                               callback:function(value){
                                  Controller.api.bindevent();
                               }
                            });
                        break;
                        case 'delAll':
                            var data = checkStatus.data;
                            var ids = '';
                            $(data).each(function (k,v) {
                                ids+=v.id+',';
                            });
                            if(data.length<=0){
                                Toastr.error('请选择要删除的数据');return;
                            }
                            Layer.confirm(
                                __('Are you sure you want to delete the %s selected item?', data.length),
                                {icon: 3, title: __('Warning'), shadeClose: true},
                                function (index) {
                                    Fast.api.ajax("kuerp/expend_type/del?ids="+ids, function () {
                                        reloadTable()
                                    },'',true);
                                    Layer.close(index);
                                }
                            );
                        break;
                    }
                });
                function reloadTable(){
                    table.reload('table', []);
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

                layui.use('laydate', function(){
                    var laydate = layui.laydate;
                    //执行一个laydate实例
                    laydate.render({
                        elem: '#checkdate',
                    });
                });

                $(document).on("click", "#checkout", function(){
                    var checkdate = $('#checkdate').val();
                    if(checkdate==''){
                        Toastr.error('请选择结账时间！');return false;
                    }
                    $.ajax( {
                        url: "kuerp/checkout/checkout",
                        type: "POST",
                        data:{
                            'checkdate':checkdate,
                        },
                        success: function(res){
                            if(res.code==1){
                                Toastr.success(res.msg);
                                $('.btn-refresh').click();
                            }else{
                                Toastr.error(res.msg);
                            }
                        }
                    });
                });

                $(document).on("click", "#checkoutf", function(){
                    $.ajax( {
                        url: "kuerp/checkout/checkoutf",
                        type: "POST",
                        data:{
                            'checkdate':'',
                        },
                        success: function(res){
                            if(res.code==1){
                                Toastr.success(res.msg);
                                $('.btn-refresh').click();
                            }else{
                                Toastr.error(res.msg);
                            }
                        }
                    });
                });

                Form.api.bindevent($("form[role=form]"), function(data, ret){
                    parent.location.reload();
                });
            }
        }
    };
    return Controller;
});