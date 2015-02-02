var input = document.getElementById("id_duration");
input.onchange = function () {
    var duration = "";

    input = document.getElementById("id_duration").value;
    duration = Math.round(input / 2);
    document.getElementById("id_presence").value = duration;
}
