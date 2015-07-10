function change(id) {
    // Theses functions will be called in the choose categories page.
    // This will grab the input element.
    var button = document.getElementById(id);
    // check whether the submit button is enabled.
    if (button.disabled === true) {
        button.disabled = false; // if it is then disable it.
    } else {
        button.disabled = true; // otherwise enable it.
    }
}

window.onload = function () {

    var checkboxes = document.getElementsByName('category[]');
    var radioButtons = document.getElementsByName('isdefault');

    for (var i = 0; i < checkboxes.length; i++) {
        if (checkboxes[i].checked === false) {
            var checboxValue = checkboxes[i].value;
            if (radioButtons[i].value == checboxValue.split("$", 1)) {
                radioButtons[i].disabled = true;
            }
        }
    }

}
