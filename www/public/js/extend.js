$('li.dropdown').mouseover(function() {   
    $(this).addClass('open');
}).mouseout(function() {
    $(this).removeClass('open');
}); 

$(function () {
    $('#account').popover('show');
})

$("#query").click(function(){
    var index = layer.load(2, {shade: [0.7,'#fff'], time: 5000});
});
