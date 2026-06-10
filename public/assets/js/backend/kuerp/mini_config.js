define(['jquery', 'bootstrap', 'backend', 'table', 'form', '/assets/addons/kuerp/layui/layui.js'], function ($, undefined, Backend, Table, Form, Layui) {

    var Controller = {
        index: function () {
            layui.use('element', function(){
                // 初始化选项卡
            });
            Controller.api.bindevent();
        },
        edit: function () {
            Controller.api.bindevent();
        },
        api: {
            bindevent: function () {
                $(document).on("click", ".sub_btn", function(){
                    var id = $(this).data('id');
                    var form = $('#'+id);
                    // 同步编辑器内容到 textarea 后再序列化
                    form.find('.editor').each(function(){
                        if($(this).data('summernote')){
                            $(this).val($(this).summernote('code'));
                        }
                    });
                    $.ajax({
                        url: "kuerp/mini_config/index",
                        type: "POST",
                        data: form.serialize(),
                        success: function(res){
                            if(res.code==1){
                                Toastr.success('操作成功');
                            }else{
                                Toastr.error(res.msg);
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
