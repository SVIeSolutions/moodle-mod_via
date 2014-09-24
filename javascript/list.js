
function change(id) {
    // this will grab the input element
    var button = document.getElementById(id);
    // check whether the submit button is enabled
    if (button.disabled == true) {
        button.disabled = false; // if it is then disable it
    } else {
        button.disabled = true; // otherwise enable it
    }
}

var checkboxes = document.getElementsByName('category[]');
var radioButtons = document.getElementsByName('isdefault');

for (var i = 0; i < checkboxes.length; i++) {

    if (checkboxes[i].checked == false) {
        var checboxValue = checkboxes[i].value;
        var checkboxVal = checboxValue.split("/", 1);
       
       if (radioButtons[i].value == checkboxVal) {
           radioButtons[i].disabled = true;
       }
    }
}

