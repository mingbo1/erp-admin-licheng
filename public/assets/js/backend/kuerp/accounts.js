define(['jquery', 'bootstrap', 'backend', 'table', 'form', '/assets/addons/kuerp/layui/layui.js'], function ($, undefined, Backend, Table, Form, Layui) {

    var Controller = {
        index: function () {

            layui.use(['table','upload'], function(){
                var table = layui.table;
                upload = layui.upload;

                table.render({
                    elem: '#table'
                    ,url:'kuerp/accounts/index'
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
                    ,cellMinWidth: 100 //全局定义常规单元格的最小宽度，layui 2.2.1 新增
                    ,cols: [[
                        {checkbox: true},
                        {field: 'account_coding', title: __('Account_coding')},
                        {field: 'account_name', title: __('Account_name')},
                        {field: 'balance', title: __('Balance'),sort:true},
                        {field: 'start_account_balance', title: __('Start_account_balance'), operate:'BETWEEN'},
                        {field: 'start_account_time', title: __('Start_account_time'),templet: function (d) {
                                return layui.util.toDateString(d.start_account_time*1000,'yyyy-MM-dd');
                            }},
                        {field: 'account_type_id', title: __('Account_type_id'),templet: function (d) {if(d.account_type_id==1){
                              return __('Account_type_id 1');
                           }if(d.account_type_id==2){
                              return __('Account_type_id 2');
                           }}},
                        {field: 'is_default', title: __('Is_default'),templet: function (d) {if(d.is_default==0){
                              return __('Is_default 0');
                           }if(d.is_default==1){
                              return __('Is_default 1');
                           }}},
                        {field: 'updatetime', title: __('Updatetime'),templet: function (d) {
                                return layui.util.toDateString(d.updatetime*1000);
                            }},
                        {fixed: 'right', title:__('Operate'), toolbar: '#barDemo', width:150}
                    ]]
                });

                //头工具栏事件
                table.on('toolbar(table)', function(obj){
                    var checkStatus = table.checkStatus(obj.config.id);
                    var data = checkStatus.data;
                    var ids = '';
                    $(data).each(function (k,v) {
                        ids+=v.id+',';
                    });
                    switch(obj.event){
                        case 'refresh':
                            table.reload('table', []);
                            break;
                        case 'add':
                            Fast.api.open("kuerp/accounts/add", "添加", {
                               callback:function(value){
                                  Controller.api.bindevent();
                               }
                            });
                        break;
                        case 'delAll':
                            if(data.length<=0){
                                Toastr.error('请选择要删除的数据');return;
                            }
                            Layer.confirm(
                                __('Are you sure you want to delete the %s selected item?', data.length),
                                {icon: 3, title: __('Warning'), shadeClose: true},
                                function (index) {
                                    Fast.api.ajax("kuerp/accounts/del?ids="+ids, function () {
                                        $('.btn-refresh').click();
                                    },'',true);
                                    Layer.close(index);
                                }
                            );
                        break;
                    }
                });

                //监听行工具事件
                table.on('tool(table)', function(obj){
                    var data = obj.data;
                    if(obj.event === 'del'){
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
                            {icon: 3, title: __('Warning'),offset: [top, left], shadeClose: true},
                            function (index) {
                                $.ajax( {
                                    url: "kuerp/accounts/del",
                                    type: "POST",
                                    data:{'ids':data.id},
                                    success: function(res){
                                        if(res.code==1){
                                            Toastr.success('操作成功');
                                            setTimeout(function () {
                                                $('.btn-refresh').click();
                                            },1000)
                                        }else{
                                            Toastr.error(res.msg);
                                        }
                                    }
                                });
                                Layer.close(index);
                            }
                        );
                    } else if(obj.event === 'edit'){
                        Fast.api.open("kuerp/accounts/edit?ids="+data.id, "编辑");
                    }
                });
                //搜索事件
                $('#search').on('click', function () {
                    table.reload('table', {
                        where: {
                            filter:JSON.stringify(getSearch()),
                            op:JSON.stringify(getOp()),
                        }
                        ,page: {
                            curr: 1
                        }
                    });
                });
                function getSearch() {
                    var search = {};
                    var account_coding = $('#account_coding').val();
                    if(account_coding!=''){
                        search.account_coding = account_coding;
                    }
                    var account_name = $('#account_name').val();
                    if(account_name!=''){
                        search.account_name = account_name;
                    }
                    var start_account_time = $('#start_account_time').val();
                    if(start_account_time!=''){
                        search.start_account_time = start_account_time;
                    }
                    var account_type_id = $('#account_type_id').val();
                    if(account_type_id!=''){
                        search.account_type_id = account_type_id;
                    }
                    var is_default = $('#is_default').val();
                    if(is_default!=''){
                        search.is_default = is_default;
                    }
                    return search;
                }
                function getOp() {
                    var op = {};
                    op.account_coding = 'LIKE';
                    op.account_name = 'LIKE';
                    op.start_account_time = 'RANGE';
                    return op;
                }
            });
            Controller.api.bindevent();
        },
        
        add: function () {
            Controller.api.bindevent();
        },
        edit: function () {
            Controller.api.bindevent(false);
        },
        api: {
            bindevent: function (reloadStatus=true) {
                layui.use('laydate', function(){
                    var laydate = layui.laydate;
                    //执行一个laydate实例
                    
                    laydate.render({
                        elem: '#c-start_account_time',
                        type: 'datetime'
                    });
                    laydate.render({
                         elem: '#start_account_time',
                         type: 'datetime',
                         range: true
                    });
                });
                Form.api.bindevent($("form[role=form]"), function(data, ret){
                    if(reloadStatus){
                        parent.location.reload();
                    }else{
                        $('.btn-refresh').click();
                    }
                });
            }
        }
    };
    return Controller;
});