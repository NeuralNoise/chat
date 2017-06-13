"use strict";

var css = document.createElement('link');
css.type = 'text/css';
css.rel = 'stylesheet';
css.href = '//resultlead.ru/mychat/css/mychat-admin.css?d='+ new Date().getTime();
document.getElementsByTagName('head')[0].appendChild(css);

var admin_container = document.getElementById('admin-container');
var top_pos = admin_container.getBoundingClientRect().top;
var window_height = window.innerHeight;
var height = window_height - top_pos;
admin_container.style.height = window.innerHeight + 'px';

(function () {

    var srvaddress = 'http://resultlead.ru/mychat/server/';
    var adminaddress = srvaddress+'admin.php?';
    var logfile = 'http://resultlead.ru/mychat/log/log.php?f=server&';
    var errorfile = 'http://resultlead.ru/mychat/log/log.php?f=error&';

    var xhttp, xhttplog, xhttperror, xhttpauth;

    ////////////////////////////////////////////////////////////////////////////

    var init = function () {
        var mychatsetcookie = function (cname) {
            var cvalue = Math.random().toString(36).substring(2) + (new Date()).getTime().toString(36);
            var exdays = 365;
            var d = new Date();
            d.setTime(d.getTime() + (exdays*24*60*60*1000));
            var expires = "expires="+ d.toUTCString();
            document.cookie = cname + "=" + cvalue + ";" + expires + ";path=/";
            return cvalue;
        }

        if(document.getElementById('admin-status-refresh') == null) {

            var b64data = '';
            document.getElementById("register-image").onchange = function(){
                var reader = new FileReader();
                var file = document.getElementById('register-image').files[0];
                if (file) {
                    reader.readAsDataURL(file);
                    reader.addEventListener('loadend', function () {
                        b64data = reader.result;
                    });
                }
            };

            document.getElementById('goregister').onclick = function () {

                var message = document.getElementById('loginmsg');
                var name = document.getElementById("register-name").value;

                if(name.length == 0) {
                    message.className = 'error';
                    message.innerHTML = 'Поле <b>Имя</b> обязательно для заполнения.';
                }
                else {
                    document.getElementById('goregister').disabled = true;
                    xhttpauth = new XMLHttpRequest();
                    xhttpauth.open('POST', adminaddress, true);
                    xhttpauth.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');

                    cvalue = Math.random().toString(36).substring(2) + (new Date()).getTime().toString(36);
                    var params = 'action=register' +
                        '&mychatcookid=' + encodeURIComponent(cvalue) +
                        '&name=' + encodeURIComponent(name) +
                        '&image=' + encodeURIComponent(b64data);

                    xhttpauth.send(params);
                    xhttpauth.onreadystatechange = function () {
                        if (xhttpauth.readyState == 4) {

                            var json = eval('(' + xhttpauth.responseText + ')');
                            if (json.msg == 1) {
                                var mychatcookid = mychatsetcookie('mychatcookid');
                                message.className = 'success';
                                message.innerHTML = 'Регистрация прошла успешно.';
                                document.location.reload();
                            }
                            else if (json.msg == 2) {
                                message.className = 'error';
                                message.innerHTML = 'Регистрация не завершена. Что-то пошло не так.';
                            }
                            else if (json.msg == 3) {
                                message.className = 'error';
                                message.innerHTML = 'Регистрация не завершена. Пользователь с таким Client ID уже существует.';
                            }
                            document.getElementById('goregister').disabled = false;
                        }
                    }
                }
            };

            return;
        }

        loaddataloop();

        document.getElementById('admin-start').onclick = function () {
            loaddata('act=start');
        };

        document.getElementById('admin-stop').onclick = function () {
            loaddata('act=stop');
        };

        document.getElementById('admin-status-refresh').onclick = function () {
            loaddata('act=status');
        };

        document.getElementById('admin-logfile-refresh').onclick = function () {
            load_log();
        };

        document.getElementById('admin-errorfile-refresh').onclick = function () {
            load_errors();
        };

        var navButtons = document.getElementsByClassName('admin-nav-button');
        for(var i = 0; i < navButtons.length; i++) {
            navButtons[i].onclick = function () {

                var target = this.getAttribute('data-target');
                console.log(target);

                var contentBlocks = document.getElementsByClassName('content-block');
                for(var j = 0; j < contentBlocks.length; j++) {
                    contentBlocks[j].style.left = '10000px';
                    contentBlocks[j].style.right = '20000px';
                }
                document.getElementById(target).style.left = '0';
                document.getElementById(target).style.right = '0';

                for(var c = 0; c < navButtons.length; c++) {
                    navButtons[c].classList.remove('active');
                }
                this.classList.add('active');

                return false;
            };
        }
    };

    var loaddata = function(act) {
        if(document.getElementById('admin-status-refresh') == null) return; //Если в режиме авторизации - не обрабатываем

        document.getElementById('admin-status-refresh').disabled = true;

        xhttp = new XMLHttpRequest();
        xhttp.open('GET',adminaddress+act,true);
        xhttp.send();
        xhttp.onreadystatechange = function(){
            if (xhttp.readyState == 4){

                var json = eval( '('+xhttp.responseText+')' );

                if(json.msg == -1) location.reload(); //Если пришел сигнал о том, что пользователь не авторизован, перезагружаем страницу
                document.getElementById('admin-status').style.color = json.color;
                document.getElementById('admin-status').innerHTML = json.msg;
                document.getElementById('admin-status-refresh').disabled = false;
            }
        }
    };


    var load_log = function() {
        if(document.getElementById('admin-status-refresh') == null) return; //Если в режиме авторизации - не обрабатываем

        document.getElementById('admin-logfile-refresh').disabled = true;
        xhttplog = new XMLHttpRequest();
        xhttplog.open('GET',logfile+Math.random(),true); //Добавляем случайное число, чтобы избежать проблем с кешированием
        xhttplog.send();
        xhttplog.onreadystatechange = function(){
            if (xhttplog.readyState == 4){
                //Принятое содержимое файла должно быть опубликовано
                document.getElementById('admin-logfile').innerHTML = xhttplog.responseText;
                document.getElementById("admin-logfile").scrollTop = document.getElementById("admin-logfile").scrollHeight;
                document.getElementById('admin-logfile-refresh').disabled = false;
            }
        }
    };

    var load_errors = function() {
        if(document.getElementById('admin-status-refresh') == null) return; //Если в режиме авторизации - не обрабатываем

        document.getElementById('admin-errorfile-refresh').disabled = true;
        xhttperror = new XMLHttpRequest();
        xhttperror.open('GET',errorfile+Math.random(),true); //Добавляем случайное число, чтобы избежать проблем с кешированием
        xhttperror.send();
        xhttperror.onreadystatechange = function(){
            if (xhttperror.readyState == 4){
                //Принятое содержимое файла должно быть опубликовано
                document.getElementById('admin-errorfile').innerHTML = '<pre>'+xhttperror.responseText+'</pre>';
                document.getElementById("admin-errorfile").scrollTop = document.getElementById("admin-errorfile").scrollHeight;
                document.getElementById('admin-errorfile-refresh').disabled = false;
            }
        }
    };


    //////////////////////////////MAIN LOOP/////////////////////////////////////

    var loaddataloop = function () {
        if(document.getElementById('admin-status-refresh') == null) return; //Если в режиме авторизации - не обрабатываем
        loaddata('act=status');
        load_log();
        load_errors();
        setTimeout(loaddataloop, 30000);
    };

    return {

        load : function () {
            window.addEventListener('load', function () {
                init();
            }, false);
        }
    }
})().load();
