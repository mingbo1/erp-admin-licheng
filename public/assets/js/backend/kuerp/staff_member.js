define(['jquery', 'bootstrap', 'backend', 'table', 'form', '/assets/addons/kuerp/layui/layui.js'], function ($, undefined, Backend, Table, Form, Layui) {

    var Controller = {
        index: function () {

            layui.use(['table','upload','form'], function(){
                var table = layui.table;
                upload = layui.upload;

                table.render({
                    elem: '#table'
                    ,url:'kuerp/staff_member/index'
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
                        {field: 'men_code', title: __('Men_code'), operate: 'LIKE'},
                        {field: 'name', title: __('Name'), operate: 'LIKE'},
                        {field: 'phone', title: __('Phone'), operate: 'LIKE'},
                        {field: 'type_text', title: __('Type')},
                        {field: 'admin_nickname', title: __('Admin_id'), operate: 'LIKE'},
                        {field: 'updatetime', title: __('Updatetime'),templet: function (d) {
                                return layui.util.toDateString(d.updatetime*1000);
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
                            Fast.api.open("kuerp/staff_member/add", "添加", {
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
                                    Fast.api.ajax("kuerp/staff_member/del?ids="+ids, function () {
                                        $('.btn-refresh').click();
                                    },'',true);
                                    Layer.close(index);
                                }
                            );
                        break;
                        case 'import':
                            Fast.api.open("kuerp/staff_member/import", "导入", {
                                callback:function(value){
                                    Controller.api.bindevent();
                                }
                            });
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
                                    url: "kuerp/staff_member/del",
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
                        Fast.api.open("kuerp/staff_member/edit?ids="+data.id, "编辑");
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
                    var men_code = $('#men_code').val();
                    if(men_code!=''){
                        search.men_code = men_code;
                    }
                    var name = $('#name').val();
                    if(name!=''){
                        search.name = name;
                    }
                    var phone = $('#phone').val();
                    if(phone!=''){
                        search.phone = phone;
                    }
                    var type = $('#type').val();
                    if(type!=''){
                        search.type = type;
                    }
                    var status = $('#status').val();
                    if(status!=''){
                        search.status = status;
                    }
                    return search;
                }
                function getOp() {
                    var op = {};
                    op.men_code = 'LIKE';
                    op.name = 'LIKE';
                    op.phone = 'LIKE';
                    return op;
                }
            });
            Controller.api.bindevent();
        },
        import: function () {
            $(document).on("click", ".step-btn_btn", function(){
                var rel = $(this).data('rel');
                $('.ui_buttons').hide();
                $('.step_'+rel).show();
                $('.step-item').hide();
                $('#import-'+rel).show();

                $('.steps-ul').find('li').removeClass('step1-cur');
                $('.steps-ul').find('li').removeClass('step2-cur');
                $('.steps-ul').find('li').removeClass('step3-cur');
                if(!$('.step1-base').hasClass('step1-nol')){
                    $('.step1-base').addClass('step1-nol');
                }
                if(!$('.step2-base').hasClass('step2-nol')){
                    $('.step2-base').addClass('step2-nol');
                }
                if(!$('.step3-base').hasClass('step3-nol')){
                    $('.step3-base').addClass('step3-nol');
                }
                $('.'+rel+'-base').addClass(rel+'-cur');
            });
            $(document).on("click", ".btn-close", function(){
                parent.layer.closeAll()
            });
            $(document).on("click", ".finish_btn", function(){
                parent.layer.closeAll();
                parent.location.reload();
            });
            $(document).on("click", ".chk", function(){
                if(!$(this).hasClass('checked')){
                    $(this).addClass('checked');
                }else{
                    $(this).removeClass('checked');
                }
                if($('.quantity').hasClass('checked')){
                    $('#quantityStorage').show();
                }else{
                    $('#quantityStorage').hide();
                }
            });


            layui.use(['upload'], function(){
                upload = layui.upload;
                upload.render({
                    elem: '#upfile'
                    ,url: 'ajax/upload'
                    ,done: function(res, index, upload){ //上传后的回调
                        if(res.code==1){
                            var editstatus = $("input[name='editstatus']:checked").val();
                            $.ajax( {
                                url: "kuerp/staff_member/import",
                                type: "POST",
                                data:{
                                    'editstatus':editstatus,
                                    'file':res.data.url,
                                },
                                success: function(res){
                                    if(res.code==1){
                                        $('#addMsg').html(res.msg.add+'条');
                                        $('#updateMsg').html(res.msg.update+'条');
                                        Toastr.success('导入成功');
                                    }else{
                                        Toastr.error(res.msg);
                                    }
                                }
                            });
                        }else{
                            Toastr.error(res.msg);
                        }
                    }
                    ,accept: 'file' //允许上传的文件类型
                    ,size: 50 //最大允许上传的文件大小
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