define(['jquery', 'bootstrap', 'backend', 'table', 'form', '/assets/addons/kuerp/layui/layui.js'], function ($, undefined, Backend, Table, Form, Layui) {

    var Controller = {
        index: function () {

            layui.use(['table','upload','form'], function(){
                var table = layui.table;
                upload = layui.upload;

                var typesList = Config.types;
                var types = xmSelect.render({
                    el: '#types',
                    language: 'zn',
                    tips: '单位性质',
                    data: typesList
                });

                table.render({
                    elem: '#table'
                    ,url:'kuerp/intercourse/index'
                    ,parseData: function(res){ //res 即为原始返回的数据
                        return {
                            "code": 0, //解析接口状态
                            "msg": '', //解析提示文本
                            "count": res.total, //解析数据长度
                            "data": res.rows, //解析数据列表
                         };
                    }
                    ,toolbar: '#thisToolbar' //开启头部工具栏，并为其绑定左侧模板
                    ,cellMinWidth: 100 //全局定义常规单元格的最小宽度，layui 2.2.1 新增
                    ,cols: [[
                        {field: 'code', title: __('往来单位编号'),width:150},
                        {field: 'name', title: __('名称'),merge:['purchase_code'],width:140},
                        {field: 'type', title: __('往来单位性质'),templet: function (d) {
                                if(d.type==1){
                                    return '客户';
                                }else{
                                    return '供应商';
                                }
                            }},
                        {field: 'receive', title: __('应收款余额')},
                        {field: 'give', title: __('应付款余额')},
                    ]]
                });

                //头工具栏事件
                table.on('toolbar(table)', function(obj){
                    switch(obj.event){
                        case 'refresh':
                            table.reload('table', []);
                            break;
                    }
                });
                //搜索事件
                $('#search').on('click', function () {
                    var typesArr = types.getValue();
                    var types_id = '';
                    if(typesArr.length>0){
                        $(typesArr).each(function (k,v) {
                            if(types_id===''){
                                types_id += v.value;
                            }else{
                                types_id += ','+v.value;
                            }
                        })
                    }
                    var search = {};
                    if(types_id!=''){
                        search.types_id = types_id;
                    }
                    var keyword = $('#keyword').val();
                    if(keyword!=''){
                        search.keyword = keyword;
                    }
                    table.reload('table', {
                        where: {
                            filter:JSON.stringify(search),
                            op:JSON.stringify(getOp()),
                        }
                    });
                });
                function getOp() {
                    var op = {};
                    op.keyword = 'LIKE';
                    return op;
                }
            });
            Controller.api.bindevent();
        },
        api: {
            bindevent: function () {
                Form.api.bindevent($("form[role=form]"), function(data, ret){
                    parent.location.reload();
                });
            }
        }
    };
    return Controller;
});