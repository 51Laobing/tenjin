function the_edit_order(id) {
	layer.open({
	    type: 2,
	    title: '订单编辑',
	    shadeClose: true,
	    shade: false,
	    area: ['785px', '485px'],
	    content: ['order/edit.php?id=' + id, 'no']
	}); 
}
