define(['jquery', 'bootstrap', 'backend', 'table', 'form', '/assets/addons/kuerp/layui/layui.js'], function ($, undefined, Backend, Table, Form, Layui) {

    var Controller = {
        index: function () {
            Controller.api.bindevent();
        },
        api: {
            bindevent: function () {
                layui.use('laydate', function(){
                    var laydate = layui.laydate;
                    //执行一个laydate实例
                    laydate.render({
                        elem: '#purchase_time',
                        type: 'datetime',
                        range: true
                    });
                });

                $(document).on("click", "#search", function(){
                    getInfo();
                });

                function getInfo(){
                    var index = layer.load(0, {shade: false});
                    $.ajax( {
                        url: "kuerp/profit/index",
                        type: "POST",
                        data:{'purchase_time':$('#purchase_time').val()},
                        success: function(res){
                            layer.closeAll();
                            $('.table_time').html('日期：'+res.start_time+'至'+res.end_time);
                            $('.sale_money').html(res.data.sale_money);
                            $('.sales_discount').html(res.data.sales_discount);
                            $('.collection_all').html(res.data.collection_all);
                            $('.cost_all').html(res.data.cost_all);
                            $('.discount_all').html(res.data.discount_all);
                            $('.payment_all').html(res.data.payment_all);
                            $('.surplus_loss').html(res.data.surplus_loss);
                            $('.main_business').html(res.data.main_business);
                            $('.other_income').html(res.data.other_income);
                            $('.profit').html(res.data.profit);
                        }
                    })
                }
                getInfo();
                Form.api.bindevent($("form[role=form]"), function(data, ret){
                    parent.location.reload();
                });
            }
        }
    };
    return Controller;
});