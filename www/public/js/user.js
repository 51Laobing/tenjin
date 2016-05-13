function the_new_business(type) {
    var business = null;
    if (type == 1) {
        business = "business/order.php";
    }
    
    if (business != null) {
        window.open(business, 'popwindow','height=320,width=690,top=80,left=180,toolbar=no,menubar=no,scrollbars=no,resizable=no,location=no,status=no');
        return null;
    }
    
    alert("无法获取当前业务类型!");    
}

