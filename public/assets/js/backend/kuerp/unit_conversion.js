define(['jquery', 'bootstrap', 'backend', 'table', 'form', '/assets/addons/kuerp/layui/layui.js'], function ($, undefined, Backend, Table, Form, Layui) {

    var Controller = {
        index: function () {

            layui.use(['table','upload'], function(){
                var table = layui.table;
                upload = layui.upload;

                table.render({
                    elem: '#table'
                    ,url:'kuerp/unit_conversion/index'
                    ,parseData: function(res){ //res 即为原始返回的数据
                        console.log( res.rows);
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
                        {field: 'name', title: __('名称')},
                        {field: 'advance_rate', title: __('Advance_rate'), operate:'BETWEEN'},
                        {field: 'createtime', title: __('Createtime'),templet: function (d) {
                                return layui.util.toDateString(d.createtime*1000);
                            }},
                        {field: 'updatetime', title: __('Updatetime'),templet: function (d) {
                                return layui.util.toDateString(d.updatetime*1000);
                            }},
                        {fixed: 'right', title:__('Operate'), toolbar: '#barDemo', width:150}
                    ]]
                });

                upload.render({
                    elem: '#import'
                    ,url: 'ajax/upload'
                    ,done: function(res, index, upload){ //上传后的回调
                         if(res.code==1){
                             Fast.api.ajax("kuerp/unit_conversion/import?file="+res.data.url, "",'',true);
                         }else{
                             Toastr.error(res.msg);
                         }
                    }
                    ,accept: 'file' //允许上传的文件类型
                    ,size: 50 //最大允许上传的文件大小
                });

                //头工具栏事件
                table.on('toolbar(table)', function(obj){
                    var checkStatus = table.checkStatus(obj.config.id);
                    switch(obj.event){
                        case 'refresh':
                            table.reload('table', []);
                            break;
                        case 'add':
                            Fast.api.open("kuerp/unit_conversion/add", "添加", {
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
                                    Fast.api.ajax("kuerp/unit_conversion/del?ids="+ids, function () {
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
                                    url: "kuerp/unit_conversion/del",
                                    type: "POST",
                                    data:{'ids':data.id},
                                    success: function(res){
                                        if(res.code==1){
                                            Toastr.success('操作成功');
                                            setTimeout(function () {
                                                $('.btn-refresh').click();
                                            },1500)
                                        }else{
                                            Toastr.error(res.msg);
                                        }
                                    }
                                });
                                Layer.close(index);
                            }
                        );
                    } else if(obj.event === 'edit'){
                        Fast.api.open("kuerp/unit_conversion/edit?ids="+data.id, "编辑");
                    }
                });
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

                $(document).on("change", ".basic_unit", function(){
                    $('.basic_unit_td').html($('.basic_unit').val());
                });
                // 删除行
                $(document).on("click", ".del", function(){
                    var lawtable = document.getElementById("unit_table");
                    var rows = lawtable.rows;//获取所有行
                    if(rows.length>2){
                        $(this).parents('tr').remove();
                    }else{
                        Toastr.error('至少保留一个副单位！');
                    }
                    setTd()
                });
                // 添加行
                $(document).on("click", ".add", function(){
                    var basic_unit = $('.basic_unit').val();
                    var lawtable = document.getElementById("unit_table");
                    var rows = lawtable.rows;//获取所有行
                    var html = '<td class="text_right">\n' +
                        '                    <div class="">\n' +
                        '                        <span class="add"><i class="fa fa-plus"></i></span><span class="del"><i class="fa fa-trash"></i></span>\n' +
                        '                        <span class="unit_name">副单位'+rows.length+'</span>\n' +
                        '                    </div>\n' +
                        '                </td>\n' +
                        '                <td><div><input data-rule="required" type="text" name="row[sub_unit][]" class="goods_note" value=""></div>\n' +
                        '                <td>=</td>\n' +
                        '                <td>\n' +
                        '                    <input data-rule="required" type="number" name="row[advance_rate][]" class="advance_rate" value="">\n' +
                        '                </td>\n' +
                        '                <td class="basic_unit_td">'+basic_unit+'</td>';
                    html = '<tr>'+html+'</tr>';
                    var obj = $(this).parents('tr');
                    $(obj).after(html);
                    setTd()
                });
                function setTd(){
                    var lawtable = document.getElementById("unit_table");
                    var rows = lawtable.rows;//获取所有行
                    $(rows).each(function (k,v) {
                        if(k>0){
                            $(this).find('.unit_name').html('副单位'+k);
                        }
                    })
                }

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