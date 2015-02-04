function testConnection() {
    // This function will open a popup window to test the server parameters for successful connection.
    // This function is called when the button 'test connexion' is clicked in the settings page.
    if ((document.getElementById('id_s_via_via_apiurl').value.length === 0) || (document.getElementById('id_s_via_via_apiurl').value === '')) {
        alert("L'URL de l'API est vide! / The URL for the API is empty!");
        return false;
    }

    var queryString = "";

    queryString += "apiurl=" + escape(document.getElementById('id_s_via_via_apiurl').value);
    queryString += "&cleid=" + escape(document.getElementById('id_s_via_via_cleid').value);
    queryString += "&apiid=" + escape(document.getElementById('id_s_via_via_apiid').value);

    return openpopup(null, {url:'/mod/via/conntest.php?' + queryString, name:'connectiontest', options: 'scrollbars=yes,resizable=no,width=760,height=400'});
}

function testAdminId() {
    // This function will open a popup window to test the user permissions of the admin id provided.
    // This function is called when the button 'test admin' is clicked in the settings page.
    if ((document.getElementById('id_s_via_via_apiurl').value.length === 0) || (document.getElementById('id_s_via_via_apiurl').value === '')) {
        alert("L'URL de l'API est vide! / The URL for the API is empty!");
        return false;
    }

    var queryString = "";

    queryString += "apiurl=" + escape(document.getElementById('id_s_via_via_apiurl').value);
    queryString += "&cleid=" + escape(document.getElementById('id_s_via_via_cleid').value);
    queryString += "&apiid=" + escape(document.getElementById('id_s_via_via_apiid').value);
    queryString += "&adminid=" + escape(document.getElementById('id_s_via_via_adminid').value);

    return openpopup(null, { url: '/mod/via/testadminid.php?' + queryString, name: 'testadminid', options: 'scrollbars=yes,resizable=no,width=760,height=400' });
}

var categories = document.getElementById("id_s_via_via_categories");
if (categories) {
    categories.onclick = function () {
        if (this.checked) {
            document.getElementById("choosecategories").className = " ";
        } else {
            document.getElementById("choosecategories").className = "hide";
        }
    };
}
