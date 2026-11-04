define(['jquery', 'bootstrap', 'backend', 'table', 'form', 'upload', '/assets/addons/kuerp/layui/layui.js'], function ($, undefined, Backend, Table, Form, Upload, Layui) {

    var Controller = {
        index: function () {

            layui.use(['table','upload','form','laydate'], function(){
                var table = layui.table;
                var laydate = layui.laydate;
                upload = layui.upload;

                //上传日期区间搜索
                laydate.render({
                    elem: '#upload_date',
                    type: 'date',
                    range: true
                });

                table.render({
                    elem: '#table'
                    ,url:'kuerp/enclosuresale/index'
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
                        {field: 'name', title: __('文件名称'), operate: 'LIKE', templet: function (d) {
                                return "<span title='"+Controller.api.escapeHtml(d.name)+"'>"+Controller.api.escapeHtml(d.name)+"</span>";
                            }},
                        {field: 'url', title: __('原始单据'), operate: false,templet: function (d) {
                                return Controller.api.fileCell(d);
                            }},
                        {field: 'upload_date', title: __('上传日期')},
                        {field: 'createtime', title: __('Createtime'),templet: function (d) {
                                return layui.util.toDateString(d.createtime*1000);
                            }},
                        {fixed: 'right', title:__('Operate'), toolbar: '#barDemo', width:150}
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
                            Fast.api.open("kuerp/enclosuresale/add", "添加", {
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
                                    Fast.api.ajax("kuerp/enclosuresale/del?ids="+ids, function () {
                                        reloadTable()
                                    },'',true);
                                    Layer.close(index);
                                }
                            );
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
                                    url: "kuerp/enclosuresale/del",
                                    type: "POST",
                                    data:{'ids':data.id},
                                    success: function(res){
                                        if(res.code==1){
                                            Toastr.success('操作成功');
                                            setTimeout(function () {
                                                reloadTable();
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
                        Fast.api.open("kuerp/enclosuresale/edit?ids="+data.id, "编辑");
                    }
                });
                //搜索事件
                $('#search').on('click', function () {
                    reloadTable();
                });
                function getSearch() {
                    var search = {};
                    var name = $('#name').val();
                    if(name!=''){
                        search.name = name;
                    }
                    var upload_date = $('#upload_date').val();
                    if(upload_date!=''){
                        //upload_date 是 varchar('Y-m-d')：用 BETWEEN 直接比字符串。
                        //不能用 RANGE——服务端会转成 BETWEEN TIME，把日期当时间戳换算成数字，与字符串列比较恒不匹配
                        search.upload_date = upload_date.replace(' - ', ',');
                    }
                    return search;
                }
                function getOp() {
                    var op = {};
                    op.name = 'LIKE';
                    op.upload_date = 'BETWEEN';
                    return op;
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
        selectpage: function () {
            layui.use(['table','upload','form'], function(){
                var table = layui.table;
                upload = layui.upload;

                table.render({
                    elem: '#choseTable'
                    ,url:'kuerp/enclosuresale/index'
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
                        {checkbox: true},
                        {field: 'name', title: __('文件名称'), operate: 'LIKE', templet: function (d) {
                                return "<span title='"+Controller.api.escapeHtml(d.name)+"'>"+Controller.api.escapeHtml(d.name)+"</span>";
                            }},
                        {field: 'url', title: __('原始单据'), operate: false,templet: function (d) {
                                return Controller.api.fileCell(d, 'showImg');
                            }},
                        {field: 'createtime', title: __('Createtime'),templet: function (d) {
                                return layui.util.toDateString(d.createtime*1000);
                            }},
                    ]]
                });
                table.on('toolbar(table)', function(obj){
                    var checkStatus = table.checkStatus(obj.config.id);
                    switch(obj.event){
                        case 'sure':
                            var data = checkStatus.data;
                            var ids = [];
                            $(data).each(function (k,v) {
                                ids.push(v.id)
                            });
                            Fast.api.close(ids);//这里是重点，将这个ids 传至父页面
                            break;
                    }
                });
                //头工具栏事件
                $(document).on("click", "#cancel", function(){
                    Fast.api.close();
                });
                //按文件名称搜索
                $('#search').on('click', function () {
                    table.reload('choseTable', {
                        where: {
                            filter: JSON.stringify(Controller.api.getSearch()),
                            op: JSON.stringify({name:'LIKE'}),
                        }
                        ,page: {
                            curr: 1
                        }
                    });
                });
            });
            Controller.api.bindevent();
        },
        api: {
            //文件名可能带引号/尖括号，拼进 HTML 前必须转义，否则整行渲染会被打断
            escapeHtml: function (s) {
                return String(s == null ? '' : s)
                    .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
            },
            //文件单元格：图片直接显示缩略图，其余类型显示文件图标并链接到文件本身
            fileCell: function (d, imgClass) {
                if (!d.url) {
                    return '';
                }
                var url = Controller.api.escapeHtml(d.url);
                if (d.is_image == 1) {
                    return "<a href='javascript:;' class='" + (imgClass || 'showImg') + "' data-src='" + url + "'>" +
                        "<img style='max-width:100px;max-height:100px;' src='" + url + "'></a>";
                }
                var icon = Fast.api.fixurl('ajax/icon') + '?suffix=' + (d.file_suffix || 'file');
                return "<a href='" + url + "' target='_blank' title='" + __('点击查看/下载') + "'>" +
                    "<img style='max-width:48px;max-height:48px;' src='" + icon + "'> <span>" +
                    Controller.api.escapeHtml((d.file_suffix || '').toUpperCase()) + "</span></a>";
            },
            //按文件名称搜索（搜索栏的输入框 id=name）
            getSearch: function () {
                var search = {};
                var name = $('#name').val();
                if (name != '') {
                    search.name = name;
                }
                return search;
            },
            bindevent: function () {
                Form.api.bindevent($("form[role=form]"), function(data, ret){
                    parent.location.reload();
                });

                //上传成功后把原始文件名填入「文件名称」（仅在用户还没填写时，去掉扩展名）
                if (typeof Upload !== 'undefined' && Upload.api && Upload.api.custom) {
                    Upload.api.custom.enclosureName = function (data, ret, up, file) {
                        var nameInput = $('#c-name');
                        if (nameInput.length && $.trim(nameInput.val()) === '' && file && file.name) {
                            nameInput.val(file.name.replace(/\.[^.]+$/, '')).trigger('change');
                        }
                    };
                }

                $(document).on("click", ".showImg", function(){
                    var src = $(this).data('src') || $(this).find('img').attr('src') || $(this).attr('src');
                    if (!src) {
                        return;
                    }
                    layer.open({
                        type: 1,
                        title: false,
                        closeBtn: 0,
                        shadeClose: true,
                        content: "<img style='width: 1000px;' src=" + src + " />"
                    });
                });
            }
        }
    };
    return Controller;
});
