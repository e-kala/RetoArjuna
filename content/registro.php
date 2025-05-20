<div class="container mt-4" style="margin-bottom: 35px; margin-top: 143px !important;  ">
  <div class="row justify-content-center">
    <h1 class="text-center">Regístrate</h1>
    <div class="col-sm-6" style="background-color: #f7931e; padding: 30px; border-radius: 10px;">
      <!-- Pills navs -->
      <ul class="nav nav-pills nav-justified mb-3" id="ex1" role="tablist">
        <li class="nav-item" role="presentation">
          <a class="nav-link bg-light" id="tab-login" data-mdb-pill-init href="#pills-login" role="tab"
            aria-controls="pills-login" aria-selected="true">Ingresar</a>
        </li>
        <li class="nav-item" role="presentation">
          <a class="nav-link bg-light"  id="tab-register" data-mdb-pill-init href="#pills-register" role="tab"
            aria-controls="pills-register" aria-selected="false">Registrarse</a>
        </li>
      </ul>
      <!-- Pills navs -->

      <!-- Pills content -->
      <div class="tab-content">
        <div class="tab-pane fade show active" id="pills-login" role="tabpanel" aria-labelledby="tab-login">
          <form>
            <div class="text-center mb-3">
              <p>Ingresa con:</p>
              <!--
              <button type="button" data-mdb-button-init data-mdb-ripple-init class="btn btn-link btn-floating mx-1">
                <i class="fab fa-facebook-f"></i>
              </button>
              -->

              <button type="button" data-mdb-button-init data-mdb-ripple-init class="btn btn-link btn-floating mx-1">
                <!--<i class="fab fa-google"></i>-->
                <div id="g_id_onload"
                  data-client_id="121887533073-18o653lrha5rc3tdm6t533067vjv6bgi.apps.googleusercontent.com"
                  data-callback="handleCredentialResponse">
                </div>
                <div class="g_id_signin" data-type="standard"></div>
              </button>

              <!--
              <button type="button" data-mdb-button-init data-mdb-ripple-init class="btn btn-link btn-floating mx-1">
                <i class="fab fa-twitter"></i>
              </button>

              <button type="button" data-mdb-button-init data-mdb-ripple-init class="btn btn-link btn-floating mx-1">
                <i class="fab fa-github"></i>
              </button>
              -->
            </div>

            <p class="text-center">o:</p>

            <!-- Email input -->
            <div data-mdb-input-init class="form-outline mb-4">
              <input type="email" id="loginName" class="form-control" />
              <label class="form-label" for="loginName">Correo electrónico o usuario</label>
            </div>

            <!-- Password input -->
            <div data-mdb-input-init class="form-outline mb-4">
              <input type="password" id="loginPassword" class="form-control" />
              <label class="form-label" for="loginPassword">Contraseña</label>
            </div>

            <!-- 2 column grid layout -->
            <div class="row mb-4">
              <div class="col-md-6 d-flex justify-content-center">
                <!-- Checkbox -->
                <div class="form-check mb-3 mb-md-0">
                  <input class="form-check-input" type="checkbox" value="" id="loginCheck" checked />
                  <label class="form-check-label" for="loginCheck"> Recordarme </label>
                </div>
              </div>

              <div class="col-md-6 d-flex justify-content-center">
                <!-- Simple link -->
                <a href="#!">Olvidaste la contraseña?</a>
              </div>
              <!-- Submit button -->
              <button type="submit" data-mdb-button-init data-mdb-ripple-init class="btn btn-primary btn-block mb-4">Registrarme</button>
            </div>


            <!-- Register buttons -->
            <div class="text-center">
              <p>¿Aún no eres miembro? <a href="#!">Regístrate Aquí</a></p>
            </div>
          </form>
        </div>
        <div class="tab-pane fade" id="pills-register" role="tabpanel" aria-labelledby="tab-register">
          <form>
            <div class="text-center mb-3">
              <p>Sign up with:</p>
              <button type="button" data-mdb-button-init data-mdb-ripple-init class="btn btn-link btn-floating mx-1">
                <i class="fab fa-facebook-f"></i>
              </button>

              <button type="button" data-mdb-button-init data-mdb-ripple-init class="btn btn-link btn-floating mx-1">
                <i class="fab fa-google"></i>
              </button>

              <button type="button" data-mdb-button-init data-mdb-ripple-init class="btn btn-link btn-floating mx-1">
                <i class="fab fa-twitter"></i>
              </button>

              <button type="button" data-mdb-button-init data-mdb-ripple-init class="btn btn-link btn-floating mx-1">
                <i class="fab fa-github"></i>
              </button>
            </div>

            <p class="text-center">or:</p>

            <!-- Name input -->
            <div data-mdb-input-init class="form-outline mb-4">
              <input type="text" id="registerName" class="form-control" />
              <label class="form-label" for="registerName">Name</label>
            </div>

            <!-- Username input -->
            <div data-mdb-input-init class="form-outline mb-4">
              <input type="text" id="registerUsername" class="form-control" />
              <label class="form-label" for="registerUsername">Username</label>
            </div>

            <!-- Email input -->
            <div data-mdb-input-init class="form-outline mb-4">
              <input type="email" id="registerEmail" class="form-control" />
              <label class="form-label" for="registerEmail">Email</label>
            </div>

            <!-- Password input -->
            <div data-mdb-input-init class="form-outline mb-4">
              <input type="password" id="registerPassword" class="form-control" />
              <label class="form-label" for="registerPassword">Password</label>
            </div>

            <!-- Repeat Password input -->
            <div data-mdb-input-init class="form-outline mb-4">
              <input type="password" id="registerRepeatPassword" class="form-control" />
              <label class="form-label" for="registerRepeatPassword">Repeat password</label>
            </div>

            <!-- Checkbox -->
            <div class="form-check d-flex justify-content-center mb-4">
              <input class="form-check-input me-2" type="checkbox" value="" id="registerCheck" checked
                aria-describedby="registerCheckHelpText" />
              <label class="form-check-label" for="registerCheck">
                I have read and agree to the terms
              </label>
            </div>

            <!-- Submit button -->
            <button type="submit" data-mdb-button-init data-mdb-ripple-init class="btn btn-white btn-block mb-3">Registrarme</button>
          </form>
        </div>
      </div>
      <!-- Pills content -->
    </div>
  </div>
</div>