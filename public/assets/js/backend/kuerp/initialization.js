define(['jquery', 'bootstrap', 'backend', 'table', 'form', '/assets/addons/kuerp/layui/layui.js'], function ($, undefined, Backend, Table, Form, Layui) {

    var Controller = {
        index: function () {
            Controller.api.bindevent();
        },
        api: {
            bindevent: function () {
                $(document).on("click", ".sub_btn", function(){
                    if(!document.getElementById("understand").checked){
                        Toastr.error('请先确认并勾选“我已清楚了解将产生的后果”！');return;
                    }
                    $.ajax({
                        url: "kuerp/initialization/index",
                        type: "POST",
                        data:{'type':'initialization'},
                        success: function(res){
                            if(res.code==1){
                                Toastr.success('操作成功');
                                setTimeout(function () {
                                    top.window.$("ul.nav-addtabs li").find(".fa-remove").click();
                                    parent.location.reload();
                                },1500)
                            }else{
                                Toastr.error('操作失败，请稍后再试！');
                            }
                        }
                    })
                });

                Form.api.bindevent($("form[role=form]"), function(data, ret){
                    parent.location.reload();
                });
            }
        }
    };
    return Controller;
});