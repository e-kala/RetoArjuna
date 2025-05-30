<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800">Perfil</h1>
    <!--<a href="#" class="d-none d-sm-inline-block btn btn-sm btn-primary shadow-sm"><i
                                class="fas fa-download fa-sm text-white-50"></i> Generate Report</a>-->
</div>

<div class="container">
    <div class="row">
        <div class="col-sm-6">
            <div class="input-group mb-3">
                <!-- nombre completo -->
                <input type="text" class="form-control" placeholder="Nombre Completo" aria-label="nombre"
                    aria-describedby="basic-addon1">
            </div>
        </div>
        <div class="col-sm-6">
            <div class="input-group mb-3">
                <!-- Correo -->
                <input type="text" class="form-control" placeholder="Correo@Electrónico.com" aria-label="email"
                    aria-describedby="basic-addon2">
            </div>
        </div>

    </div>
    <div class="row">
        <div class="col-sm-6">

            <!-- telefono -->
            <div class="input-group mb-3">
                <input type="tel" class="form-control" id="telefono" placeholder="WhatsApp" required>
            </div>
        </div>
        <div class="col-sm-6">
            <div class="input-group mb-3">
                <!--Fecha de nacimiento -->
                <input id="datepicker" type="text" class="form-control" placeholder="Fecha de nacimiento"
                    aria-label="fechaNacimiento" aria-describedby="basic-addon2">
            </div>
        </div>

    </div>

    <div class="row">
        <div class="col-sm-6">
            <div class="input-group mb-3">
                <!-- pais -->
                <select id="countrySelect" class="form-select" aria-label="Default select example">
                    <option selected>Selecciona tu país de residencia</option>
                </select>
            </div>
        </div>
        <div class="col-sm-6">
            <div class="input-group mb-3">
                <!-- Estado -->
                <select id="countrySelect" class="form-select" aria-label="Default select example">
                    <option selected>Estado o provincia</option>
                </select>
            </div>
        </div>

    </div>
    <div class="row">
        <div class="col-sm-6">
            <div class="input-group mb-3">
                <!-- Ciudad -->
                <select id="countrySelect" class="form-select" aria-label="Default select example">
                    <option selected>Ciudad de residencia</option>
                </select>
            </div>
        </div>
        <div class="col-sm-6">
            <div class="input-group mb-3">
                
            </div>
        </div>

    </div>
</div>


<script>
    const apiUrl = 'https://restcountries.com/v3.1/all';

    fetch(apiUrl)
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            const countrySelect = document.getElementById('countrySelect');
            data.forEach(country => {
                const option = document.createElement('option');
                option.value = country.cca2; // Código del país
                option.textContent = country.name.common; // Nombre del país
                countrySelect.appendChild(option);
            });
        })
        .catch(error => {
            console.error('Hubo un problema con la solicitud:', error);
        });



    $(function () {
        $.datepicker.setDefaults($.datepicker.regional['es']);
        $("#datepicker").datepicker({
            yearRange: "1950:2012",
            changeMonth: true,
            changeYear: true
        });
    });


</script>