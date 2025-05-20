<div id="root"></div>
<script type="text/babel">
    function App() {
            return (
                <div>
                    <h1>¡Hola, mundo!</h1>
                    <p>Esta es una aplicación React usando un CDN.</p>
                </div>
            );
        }

        // Renderizar el componente en el DOM
        ReactDOM.render(<App />, document.getElementById('root'));
</script>