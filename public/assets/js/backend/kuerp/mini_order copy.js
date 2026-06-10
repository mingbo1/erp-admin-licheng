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

                var prints_status = Config.prints_status;
                var pay_status = Config.pay_status;
                var driver_list = Config.driver_list;
                var express_list = Config.express_list;
                if(isNaN(pay_status)){
                    pay_status = 0;
                }
                if(pay_status!=1){
                    hideStatus = true;
                }else{
                    hideStatus = false;
                }

                table.render({
                    elem: '#table'
                    ,url:'kuerp/user_order/index'
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
                        {checkbox: true,merge:['order_num']},
                        // {field: 'id', title: __('Id')},
                        {field: 'order_num', title: __('订单号'),merge:['order_num'],width:250},
                        {field: 'goods_code', title: __('商品编号')},
                        {field: 'goods_name', title: __('商品名称')},
                        {field: 'goods_image', title: __('商品图片'), operate: false,templet: function (d) {
                                return "<a href='javascript:;'><img style='height: 50px;' src="+d.goods_image+"></a>";
                            }},
                        {field: 'unit_name', title: __('单位')},
                        {field: 'attr_name', title: __('属性')},
                        {field: 'price', title: __('商品单价')},
                        {field: 'number', title: __('商品数量')},
                        // {field: 'more_number', title: __('副规格数量')},

                        {field: 'order_price', title: __('订单总计'),merge:['order_num']},
                        {field: 'status', title: __('订单状态'),templet: function (d) {
                                if(d.status==0){
                                    return __('待审核');
                                }
                                if(d.status==2){
                                    return __('待发货');
                                }
                                if(d.status==3){
                                    return __('待收货');
                                }
                                if(d.status==4){
                                    return __('已收货');
                                }
                            },merge:['order_num']},
                        {field: 'pay_status', title: __('支付状态'),templet: function (d) {
                                if(d.pay_status==0){
                                    return __('待支付');
                                }
                                if(d.pay_status==1){
                                    return __('已支付');
                                }
                                if(d.pay_status==2){
                                    return __('支付失败');
                                }
                            },merge:['order_num'],hide:hideStatus},
                        {field: 'name', title: __('收货人'),merge:['order_num']},
                        {field: 'diver_type', title: __('配送类型'),templet: function (d) {
                                if(d.diver_type==1){
                                    return __('专业配送');
                                }
                                if(d.diver_type==2){
                                    return __('到店自取');
                                }
                            },merge:['order_num'],hide:hideStatus},
                        {field: 'diver_name', title: __('配送人'),merge:['order_num']},
                        {field: 'phone', title: __('联系电话'),merge:['order_num'],width:120},
                        {field: 'address', title: __('收货地址'),merge:['order_num']},
                        {fixed: 'right', title:__('Operate'), width:210,merge:['order_num'],templet: function (d) {
                                var htmls = '';
                                if(prints_status){
                                    htmls += '<a class="layui-btn layui-btn-xs" lay-event="print_file">上传附件</a>';
                                }
                                if(d.status==0){
                                    htmls += '<a class="layui-btn layui-btn-xs" lay-event="examine">审核</a>';
                                }
                                if(d.status==2){
                                    if(d.sales_id!=null){
                                        htmls += '<a class="layui-btn layui-btn-xs" lay-event="deliver">发货</a>';
                                    }else{
                                        htmls += '<a class="layui-btn layui-btn-xs" lay-event="sales">生成销货单</a>';
                                    }
                                }
                                if(Config.del_status==1){
                                    htmls += '<a class="layui-btn layui-btn-xs layui-btn-danger" lay-event="del">删除</a>';
                                }
                                return htmls;
                            }}
                        // {fixed: 'right', title:__('Operate'), toolbar: '#thisBarDemo', width:150,merge:['order_num']}
                    ]],
                    done: function(res, curr, count){
                        tableMerge.render(this);
                        currpage = curr;
                        $(".layui-table-main  tr").each(function (index, val) {
                            $($(".layui-table-fixed .layui-table-body tbody tr")[index]).height($(val).height());
                        });

                        $("[data-field='lowerHairPath']").css('display', 'none');
                        $(".layui-table-main tr").each(function (index, val) {
                            $($(".layui-table-fixed-l .layui-table-body tbody tr")[index]).height($(val).height());
                            $($(".layui-table-fixed-r .layui-table-body tbody tr")[index]).height($(val).height());
                        });
                        //动态监听表头高度变化，冻结行跟着改变高度
                        $(".layui-table-header tr").resize(function () {
                            $(".layui-table-header tr").each(function (index, val) {
                                $($(".layui-table-fixed .layui-table-header table tr")[index]).height($(val).height());
                            });
                        });
                        //初始化高度，使得冻结行表头高度一致
                        $(".layui-table-header tr").each(function (index, val) {
                            $($(".layui-table-fixed .layui-table-header table tr")[index]).height($(val).height()-1);
                        });
                        //动态监听表体高度变化，冻结行跟着改变高度
                        $(".layui-table-body tr").resize(function () {
                            $(".layui-table-body tr").each(function (index, val) {
                                $($(".layui-table-fixed .layui-table-body table tr")[index]).height($(val).height());
                            });
                        });
                        //初始化高度，使得冻结行表体高度一致
                        $(".layui-table-body tr").each(function (index, val) {
                            $($(".layui-table-fixed .layui-table-body table tr")[index]).height($(val).height()-1);
                        });
                    }
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
                        case 'delAll':
                            if(data.length<=0){
                                Toastr.error('请选择要删除的数据');return;
                            }
                            Layer.confirm(
                                __('此操作不可恢复，你确定要删除这些数据吗?', data.length),
                                {icon: 3, title: __('Warning'), shadeClose: true},
                                function (index) {
                                    Fast.api.ajax("kuerp/user_order/del?ids="+ids, function () {
                                        reloadTable()
                                    },'',true);
                                    Layer.close(index);
                                }
                            );
                        break;
                        case 'examine':
                            if(data.length<=0){
                                Toastr.error('请选择要删除的数据');return;
                            }
                            Layer.confirm(
                                __('你确定要审核这些数据吗?', data.length),
                                {icon: 3, title: __('Warning'), shadeClose: true},
                                function (index) {
                                    Fast.api.ajax("kuerp/user_order/examine?ids="+ids, function () {
                                        reloadTable()
                                    },'',true);
                                    Layer.close(index);
                                }
                            );
                            break;
                        case 'deliver':
                            Fast.api.open("kuerp/user_order/deliver?type=1&ids="+ids, "发货");
                            /*Layer.confirm(
                                __('你确定要为此订单发货吗?', data.length),
                                {icon: 3, title: __('Warning'), shadeClose: true},
                                function (index) {
                                    Fast.api.ajax("kuerp/user_order/deliver?ids="+ids, function () {
                                        reloadTable()
                                    },'',true);
                                    Layer.close(index);
                                }
                            );*/
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
                            __('此操作不可恢复，你确定要删除此订单吗?'),
                            {icon: 3, title: __('Warning'),offset: [top, left], shadeClose: true},
                            function (index) {
                                $.ajax( {
                                    url: "kuerp/user_order/del",
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
                    } else if(obj.event === 'examine'){
                        Layer.confirm(
                            __('你确定要审核此订单吗?', data.length),
                            {icon: 3, title: __('Warning'), shadeClose: true},
                            function (index) {
                                Fast.api.ajax("kuerp/user_order/examine?ids="+data.id, function () {
                                    reloadTable()
                                },'',true);
                                Layer.close(index);
                            }
                        );
                    }else if(obj.event === 'deliver'){
                        Fast.api.open("kuerp/user_order/deliver?type=1&ids="+data.id, "发货");
                    } else if(obj.event === 'sales'){
                        Fast.api.open("kuerp/goods_sales/add?order_id="+data.id, "销货单",{ area:['90%','75%'],});
                    } else if(obj.event === 'print_file'){
                        Fast.api.open("kuerp/user_order/edit?ids="+data.id, "上传附件");
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
                    var order_num = $('#order_num').val();
                    if(order_num!=''){
                        search.order_num = order_num;
                    }
                    var createtime = $('#createtime').val();
                    if(createtime!=''){
                        search.createtime = createtime;
                    }
                    var status = $('#status').val();
                    if(status!=''){
                        search.status = status;
                    }
                    return search;
                }
                function getOp() {
                    var op = {};
                    op.order_num = 'LIKE';
                    op.createtime = 'BETWEEN';
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
        deliver: function () {
            Controller.api.bindevent();
        },
        api: {
            bindevent: function () {
                layui.use('laydate', function(){
                    var laydate = layui.laydate;
                    //执行一个laydate实例
                    laydate.render({
                        elem: '#createtime',
                        range: true
                    });
                });
                $(document).on("click", ".send_type", function(){
                    obj = document.getElementsByName("row[send_type]");
                    check_val = '';
                    for(k in obj){
                        if(obj[k].checked)
                            check_val=obj[k].value;
                    }

                    if(check_val==1){
                        $('.express').hide();
                        $('.diver').show();
                    }else{
                        $('.express').show();
                        $('.diver').hide();
                    }
                });
                Form.api.bindevent($("form[role=form]"), function(data, ret){
                    $('.btn-refresh').click();
                });
            }
        }
    };
    return Controller;
});