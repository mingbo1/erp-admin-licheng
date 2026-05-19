define(['jquery', 'bootstrap', 'backend', 'table', 'form', '/assets/addons/kuerp/layui/layui.js'], function ($, undefined, Backend, Table, Form, Layui) {

    var Controller = {
        index: function () {
            $(document).on("change", ".dome", function(){
                var obj = $(this).parents('tr');
                var id = obj.find('.id').val();
                var prefix = obj.find('.prefix'+id).val();
                var start = obj.find('.start'+id).val();
                var strlen = obj.find('.strlen'+id).val();

                var myDate = new Date();
                var FullYear = myDate.getFullYear();    //获取完整的年份(4位,1970-????)
                var FullMonth = myDate.getMonth();       //获取当前月份(0-11,0代表1月)
                if(FullMonth<10){
                    FullMonth = '0'+FullMonth;
                }
                var FullDay = myDate.getDate();        //获取当前日(1-31)
                if(FullDay<10){
                    FullDay = '0'+FullDay;
                }
                var FullHour = myDate.getHours();       //获取当前小时数(0-23)
                if(FullHour<10){
                    FullHour = '0'+FullHour;
                }
                var FullMinute = myDate.getMinutes();     //获取当前分钟数(0-59)
                if(FullMinute<10){
                    FullMinute = '0'+FullMinute;
                }
                var FullSecond = myDate.getSeconds();     //获取当前秒数(0-59)
                if(FullSecond<10){
                    FullSecond = '0'+FullSecond;
                }
                //年
                var year = document.getElementsByName('row[year'+id+']');
                if (year[0].checked) {
                    prefix = prefix+FullYear;
                }
                //月
                var month = document.getElementsByName('row[month'+id+']');
                if (month[0].checked) {
                    prefix = prefix+FullMonth;
                }
                //日
                var day = document.getElementsByName('row[day'+id+']');
                if (day[0].checked) {
                    prefix = prefix+FullDay;
                }
                //时
                var hour = document.getElementsByName('row[hour'+id+']');
                if (hour[0].checked) {
                    prefix = prefix+FullHour;
                }
                //分
                var minute = document.getElementsByName('row[minute'+id+']');
                if (minute[0].checked) {
                    prefix = prefix+FullMinute;
                }
                //秒
                var second = document.getElementsByName('row[second'+id+']');
                if (second[0].checked) {
                    prefix = prefix+FullSecond
                }
                var startlen = strlen;
                var numlen = start.toString().length;
                var cha = startlen-numlen;
                var str = '';
                for (var a=1;a<=cha;a++){
                    str = str+'0';
                }
                str = prefix+str+start;
                obj.find('.example'+id).val(str);
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
                $(document).on("click", ".sub_btn", function(){
                    var id = $(this).data('id');
                    $.ajax({
                        url: "kuerp/sets/index",
                        type: "POST",
                        data:$('#'+id).serialize(),
                        success: function(res){
                            if(res.code==1){
                                Toastr.success('操作成功');
                                setTimeout(function () {
                                    parent.window.location.reload();
                                },1500)
                            }else{
                                Toastr.error(res.msg);
                            }
                        }
                    })
                });
                $(document).on("click", "#set_taxes", function(){
                    if(!document.getElementById("set_taxes").checked){
                        $("#set_price_taxes").attr("checked", false);
                    }
                });
                $(document).on("click", "#set_price_taxes", function(){
                    if(!document.getElementById("set_taxes").checked){
                        $("#set_price_taxes").attr("checked", false);
                    }
                });
                $(document).on("click", "#set_discount", function(){
                    var set = Config.set;
                    var discount = parseInt(set.discount.is_checked);
                    if(discount==1){
                        $("#set_discount").prop("checked", true);
                        Toastr.error('您已经启用了优惠金额，不可取消！');
                    }
                });

                Form.api.bindevent($("form[role=form]"), function(data, ret){
                    parent.location.reload();
                });
            }
        }
    };
    return Controller;
});