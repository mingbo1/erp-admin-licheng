define(['jquery', 'bootstrap', 'backend', 'table', 'form', '/assets/addons/kuerp/layui/layui.js'], function ($, undefined, Backend, Table, Form, Layui) {

    var Controller = {
        index: function () {

            layui.config({
                base: '/assets/addons/kuerp/layui/module/'
            }).extend({
                tableMerge: 'tableMerge'
            });

            layui.use(['table','upload','form','tableMerge'], function(){
                var table = layui.table;
                upload = layui.upload;
                tableMerge = layui.tableMerge;

                table.render({
                    elem: '#table'
                    ,url:'kuerp/assemble_temp/index'
                    ,parseData: function(res){ //res 即为原始返回的数据
                        return {
                            "code": 0, //解析接口状态
                            "msg": '', //解析提示文本
                            "count": res.total, //解析数据长度
                            "data": res.rows //解析数据列表
                         };
                    }
                    ,toolbar: '#btnToolbar' //开启头部工具栏，并为其绑定左侧模板
                    ,page: true
                    ,cellMinWidth: 100 //全局定义常规单元格的最小宽度，layui 2.2.1 新增
                    ,cols: [[
                        {type: 'radio',merge:['temp_name']},
                        {field: 'temp_name', title: __('Temp_name'),merge:true},
                        {field: 'pname', title: __('组合件'),merge:['temp_name']},
                        {field: 'pnumber', title: __('组合件数量'),merge:['temp_name']},
                        {field: 'name', title: __('子件')},
                        {field: 'number', title: __('子件数量')},
                        {fixed: 'right', title:__('Operate'), toolbar: '#delDemo',merge:['temp_name'], width:150}
                    ]],
                    done: function(){
                        tableMerge.render(this)
                    }
                });

                table.on('toolbar(table)', function(obj){
                    var checkStatus = table.checkStatus(obj.config.id);
                    switch(obj.event){
                        case 'sure':
                            var data = checkStatus.data;
                            if(data.length<1){
                                Toastr.error('请选择模板！');return;
                            }
                            var ids = [];
                            $(data).each(function (k,v) {
                                var arr = [];
                                arr['id'] = v.id;
                                ids.push(arr)
                            });
                            Fast.api.close(ids);//这里是重点，将这个ids 传至父页面
                            break;
                    }
                });
                function reloadTable(){
                    table.reload('table', {
                        where: {
                            filter:JSON.stringify(getSearch()),
                            op:JSON.stringify(getOp()),
                        }
                        ,page: {
                            curr: 1
                        }
                    });
                }
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
                                    url: "kuerp/assemble_temp/del",
                                    type: "POST",
                                    data:{'ids':data.id},
                                    success: function(res){
                                        if(res.code==1){
                                            Toastr.success('操作成功');
                                            setTimeout(function () {
                                                reloadTable()
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
                        Fast.api.open("kuerp/assemble_temp/edit?ids="+data.id, "编辑");
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
                    var temp_name = $('#temp_name').val();
                    if(temp_name!=''){
                        search.temp_name = temp_name;
                    }
                    return search;
                }
                function getOp() {
                    var op = {};
                    op.temp_name = 'LIKE';
                    return op;
                }
                $(document).on("click", "#cancel", function(){
                    Fast.api.close();
                });
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
                        elem: '#c-purchase_time',
                        type: 'datetime'
                    });
                    laydate.render({
                         elem: '#purchase_time',
                         type: 'datetime',
                         range: true
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