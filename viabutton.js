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