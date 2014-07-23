
    function testConnection(a, b, c) {
    /// This function will open a popup window to test the server parameters for
    /// successful connection.
    
        var queryString = "";

        queryString += "apiurl=" + a;//escape(obj.s__via_apiurl.value);
        queryString += "&cleid=" + b;//scape(obj.s__via_cleid.value);
        queryString += "&apiid=" + c;//escape(obj.s__via_apiid.value);
        
        return openpopup(null, {url:'/mod/via/conntest.php?' + queryString, name:'connectiontest', options: 'scrollbars=yes,resizable=no,width=760,height=400'});
    }

    function testAdminId(a, b, c, d) {
        /// This function will open a popup window to test the server parameters for
        /// successful connection.
       
        var queryString = "";

        queryString += "apiurl=" + a;//escape(obj.s__via_apiurl.value);
        queryString += "&cleid=" + b;//scape(obj.s__via_cleid.value);
        queryString += "&apiid=" + c;//escape(obj.s__via_apiid.value);
        queryString += "&adminid=" + d;//escape(obj.s__via_adminid.value);

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
        }
    }

    

       



