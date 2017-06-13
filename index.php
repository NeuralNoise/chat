<?php session_start(); ?>

<!DOCTYPE html>
<html>
    <head>
        <meta charset="UTF-8" />
        <link rel="stylesheet" href="/mychat/css/admin.css" />
    </head>
    <body>

        <div class="admin-container">

            <h1 class="admin-panel-title">WebSocket server: панель администратора</h1>

            <?php if (isset($_SESSION['admin']['login'])): ?>

                <div class="admin-nav">
                    <a id="admin-nav-server" class="admin-nav-button admin-nav-server active" data-target="admin-server-controls" href="#" title="Сервер"></a>
                    <a id="admin-nav-dialogs" class="admin-nav-button admin-nav-dialogs" data-target="admin-dialogs" href="#" title="Диалоги"></a>
                    <a id="admin-nav-clients" class="admin-nav-button admin-nav-clients" href="#" data-target="admin-clients" title="Пользователи"></a>
                    <a id="admin-nav-operators" class="admin-nav-button admin-nav-operators" data-target="admin-operators" href="#" title="Операторы"></a>
                    <a id="admin-nav-settings" class="admin-nav-button admin-nav-settings" data-target="admin-settings" href="#" title="Настройки"></a>
                </div>

                <div class="admin-logout-holder">
                    <input id="admin-start" class="button" type="button" value="Start server" />
                    <input id="admin-stop" class="button" type="button" value="Stop server" />
                    <input id="admin-exit" class="button" type="button" value="logout" />
                </div>

                <div id="admin-server-controls" class="admin-server-controls content-block">

                    <h2>Сервер</h2>

                    <h3>Текущий статус</h3>
                    <div id="admin-status" class="info-block info-block-status">Loading...</div>
                    <input id="admin-status-refresh" class="button" type="button" value="refresh" /><br /><br />

                    <h3>Журнал</h3>
                    <div id="admin-logfile" class="info-block info-block-log">Loading...</div>
                    <input id="admin-logfile-refresh" class="button" type="button" value="refresh" /><br /><br />

                    <h3>Ошибки</h3>
                    <div id="admin-errorfile" class="info-block info-block-error">Loading...</div>
                    <input id="admin-errorfile-refresh" class="button" type="button" value="refresh" /><br /><br />

                </div>

                <div id="admin-dialogs" class="admin-dialogs content-block">
                    <h2>Диалоги</h2>
                    <div id="admin-dialogs-wrapper" class="admin-dialogs-wrapper">
                        <script type="text/javascript" src="//resultlead.ru/mychat/js/chat.js"></script>
                    </div>
                </div>

                <div id="admin-clients" class="admin-clients content-block"><h2>Пользователи</h2></div>
                <div id="admin-operators" class="admin-operators content-block"><h2>Операторы</h2></div>
                <div id="admin-settings" class="admin-settings content-block"><h2>Настройки</h2></div>

            <?php else: ?>

                <div class="content-block-login">

                    <p id="loginmsg"></p>

                    <div class="auth-form table">

                        <div class="login-form table-cell">

                            <h3>Войти</h3>

                            <div class="form-item">
                                <label>Логин:</label>
                                <input id="login" type="text" name="login" />
                            </div>

                            <div class="form-item">
                                <label>Пароль:</label>
                                <input id="password" type="password" name="password" />
                            </div>

                            <input id="gologin" class="button" type="button" value="Вход" />

                        </div>

                        <div class="register-form table-cell">

                            <h3>Регистрация</h3>

                            <div class="form-item">
                                <label>Имя<br/>(отображается в чате):</label>
                                <input id="register-name" type="text" name="register_name" />
                            </div>

                            <div class="form-item">
                                <label>Email<br/>(для уведомлений):</label>
                                <input id="register-email" type="text" name="register_email" />
                            </div>

                            <div class="form-item">
                                <label>Изображение:</label>
                                <input id="register-image" type="file" name="register_image" />
                            </div>

                            <div class="form-item">
                                <label>Логин:</label>
                                <input id="register-login" type="text" name="register_login" />
                            </div>

                            <div class="form-item">
                                <label>Пароль:</label>
                                <input id="register-password" type="password" name="register_password" />
                            </div>

                            <div class="form-item">
                                <label>Повторить пароль:</label>
                                <input id="register-repeat-password" type="password" name="register_repeat_password" />
                            </div>

                            <input id="goregister" class="button" type="button" value="Регистрация" />

                        </div>

                    </div>

                </div>

            <?php endif; ?>

        </div>

        <script src="/mychat/js/admin.js?d=4563121345" type="text/javascript"></script>

    </body>
</html>