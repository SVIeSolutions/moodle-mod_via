window.onload = function () {
    var checkbox = document.getElementById("checkbox");
    if (checkbox) {
        if (checkbox.checked) {
            document.getElementById("active").className = "viaaccessbutton active";
            document.getElementById("inactive").className = "viaaccessbutton inactive hide";
            document.getElementById("error").className = "error hide";
        } else {
            document.getElementById("active").className = "viaaccessbutton active hide";
            document.getElementById("inactive").className = "viaaccessbutton inactive";
        }
    }
};
var checkbox = document.getElementById("checkbox");
if (checkbox) {
    checkbox.onclick = function () {

        if (checkbox.checked) {
            document.getElementById("active").className = "viaaccessbutton active";
            document.getElementById("inactive").className = "viaaccessbutton inactive hide";
            document.getElementById("error").className = "error hide";
        } else {
            document.getElementById("active").className = "viaaccessbutton active hide";
            document.getElementById("inactive").className = "viaaccessbutton inactive";
        }
    };
}

var link = document.getElementById("inactive");
if (link) {
    link.onclick = function () {
        document.getElementById("error").className = "error";
    };
}
