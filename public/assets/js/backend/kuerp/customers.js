define(['jquery', 'bootstrap', 'backend', 'table', 'form', '/assets/addons/kuerp/layui/layui.js'], function ($, undefined, Backend, Table, Form, Layui) {

    var Controller = {
        index: function () {

            layui.use(['table','upload','form'], function(){
                var table = layui.table;
                upload = layui.upload;

                table.render({
                    elem: '#table'
                    ,url:'kuerp/customers/index'
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
                        {field: 'customertype_id', title: __('Customertype.type_name'),templet: function (d) {
                                if(d.customertype.type_name!=null){
                                    return d.customertype.type_name;
                                }else{
                                    return '';
                                }
                            }},
                        {field: 'customerlevel_id', title: __('Customerlevel_id'),templet: function (d) {
                                if(d.customerlevel.name!=null){
                                    return d.customerlevel.name;
                                }else{
                                    return '';
                                }
                            }},
                        {field: 'customer_code', title: __('客户编号'), operate: 'LIKE'},
                        {field: 'customer_name', title: __('Customer_name'), operate: 'LIKE'},
                        {field: 'saler', title: __('Saler'),templet: function (d) {
                                if(d.saler.name!=null){
                                    return d.saler.name;
                                }else{
                                    return '';
                                }
                            }},
                        {field: 'connet_name', title: __('Connet_name'), operate: 'LIKE'},
                        {field: 'customer_mobile', title: __('Customer_mobile'), operate: 'LIKE'},
                        {field: 'customer_phone', title: __('Customer_phone'), operate: 'LIKE'},
                        {field: 'qq_wx_email', title: __('Qq_wx_email'), operate: 'LIKE'},
                        {field: 'need_receive_account', title: __('Need_receive_account'), operate:'BETWEEN'},
                        {field: 'getgoods_address', title: __('Getgoods_address'), operate: 'LIKE'},
                        {field: 'note', title: __('Note'), operate: 'LIKE'},
                        {field: 'status', title: __('Status'),templet: function (d) {
                                if(d.status==1){
                                    return '<input type="checkbox" data-id="'+d.id+'" checked="" name="open" lay-skin="switch" lay-filter="switchList" lay-text="正常|禁用">'
                                }else{
                                    return '<input type="checkbox" data-id="'+d.id+'" name="open" lay-skin="switch" lay-filter="switchList" lay-text="正常|禁用">'
                                }
                            }},
                        {fixed: 'right', title:__('Operate'), toolbar: '#barDemo', width:150}
                    ]]
                });
                layui.form.on('switch(switchList)', function (obj) {
                    var onoff = this.checked ? '1' : '0';
                    var id = $(this).data('id');
                    $.ajax( {
                        url: "kuerp/customers/multi",
                        type: "POST",
                        data:{
                            'ids':id,
                            'params':"status="+onoff,
                        },
                        success: function(res){
                            if(res.code==0){
                                Toastr.error(res.msg);
                            }else{
                                Toastr.success('操作成功');
                            }
                        }
                    });
                });
                //头工具栏事件
                table.on('toolbar(table)', function(obj){
                    var checkStatus = table.checkStatus(obj.config.id);
                    switch(obj.event){
                        case 'refresh':
                            table.reload('table', []);
                            break;
                        case 'add':
                            Fast.api.open("kuerp/customers/add", "添加", {
                               callback:function(value){
                                  Controller.api.bindevent();
                               },
                                area:['48%','75%']
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
                                    Fast.api.ajax("kuerp/customers/del?ids="+ids, function () {
                                        $('.btn-refresh').click();
                                    },'',true);
                                    Layer.close(index);
                                }
                            );
                        break;
                        case 'import':
                            Fast.api.open("kuerp/customers/import", "导入", {
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
                                    url: "kuerp/customers/del",
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
                        Fast.api.open("kuerp/customers/edit?ids="+data.id, "编辑",{ area:['48%','75%']});
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
                    var customertype_id = $('#customertype_id').val();
                    if(customertype_id!=''){
                        search.customertype_id = customertype_id;
                    }
                    var customer_name = $('#customer_name').val();
                    if(customer_name!=''){
                        search.customer_name = customer_name;
                    }
                    var customer_mobile = $('#customer_mobile').val();
                    if(customer_mobile!=''){
                        search.customer_mobile = customer_mobile;
                    }
                    return search;
                }
                function getOp() {
                    var op = {};
                    op.customer_name = 'LIKE';
                    op.customer_mobile = 'LIKE';
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
                                url: "kuerp/customers/import",
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
        api: {
            bindevent: function (reloadStatus=true) {
                layui.use('laydate', function(){
                    var laydate = layui.laydate;
                    //执行一个laydate实例
                    laydate.render({
                        elem: '#c-account_time',
                    });
                });
                $(document).on("click", ".del", function(){
                    $(this).parents('tr').remove();
                });
                $(document).on("click", ".add", function(){
                    var obj = $(this).parents('tr');
                    var html = '<tr>\n' +
                        '                <td><div class="btn"><span class="add"><i class="fa fa-plus"></i></span><span class="del"><i class="fa fa-trash"></i></span></div></td>\n' +
                        '                <td><div><input type="text" name="user[name][]"><input type="hidden" name="user[id][]" value=""></div></td>\n' +
                        '                <td><div><input type="text" name="user[mobile][]"></div></td>\n' +
                        '                <td><div><input type="text" name="user[phone][]"></div></td>\n' +
                        '                <td><div><input type="text" name="user[qqnum][]"></div></td>\n' +
                        '                <td><div><input type="text" name="user[address][]"></div></td>\n' +
                        '                <td><div><select name="user[first][]"><option value="0">否</option><option value="1">是</option></select></div></td>\n' +
                        '            </tr>';
                    $(obj).after(html);
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