window.onload = function () {
var checkbox = document.getElementById("checkbox");
    if (checkbox){
        if (checkbox.checked) {
            document.getElementById("active").className = "accessbutton active";
            document.getElementById("inactive").className = "accessbutton inactive hide";
            document.getElementById("error").className = "error hide";
        } else {
            document.getElementById("active").className = "accessbutton active hide";
            document.getElementById("inactive").className = "accessbutton inactive";
        }
    }
}
var checkbox = document.getElementById("checkbox");
if (checkbox) {
    checkbox.onclick = function () {  
    
        if (checkbox.checked) {
            document.getElementById("active").className = "accessbutton active";
            document.getElementById("inactive").className = "accessbutton inactive hide";
            document.getElementById("error").className = "error hide";
        } else {
            document.getElementById("active").className = "accessbutton active hide";
            document.getElementById("inactive").className = "accessbutton inactive";
        }
    }
}

var link = document.getElementById("inactive");
if (link) {
    link.onclick = function () {
        document.getElementById("error").className = "error";
    }
}

//$(document).ready(function () {
//    $('input.ispublic').change(function () {

//        $this = $(this);

//        $.post(
//          "edit_review.php",
//          {
//              value: $this.val(),
//              checked: $this.is(':checked')
//          },
//          function (data) {
//              // do something with returned data
//          },
//          'json'
//        );
//    });
//});