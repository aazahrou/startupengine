<script>
    Vue.component('Editor', {
        template: '<div :id="editorId" style="width: 100%; height: 100%;"></div>',
        props: ['editorId', 'content', 'lang', 'theme'],
        data () {
            return {
                editor: Object,
                beforeContent: ''
            }
        },
        watch: {
            'content' (value) {
                if (this.beforeContent !== value) {
                    this.editor.setValue(value, 1)
                }
            }
        },
        mounted () {
            const lang = this.lang || 'text'
            const theme = this.theme || 'github'

            this.editor = window.ace.edit(this.editorId)
            this.editor.setValue(this.content, 1)

            // mode-xxx.js or theme-xxx.jsがある場合のみ有効
            this.editor.getSession().setMode(`ace/mode/${lang}`)
            this.editor.setTheme(`ace/theme/${theme}`)

            this.editor.on('change', () => {
                this.beforeContent = this.editor.getValue()
            this.$emit('change-content', this.editor.getValue())
        })
        }
    });

    Vue.directive('highlightjs', {
        deep: true,
        bind: function (el, binding) {
            // on first bind, highlight all targets
            let targets = el.querySelectorAll('code')
            targets.forEach((target) => {
                // if a value is directly assigned to the directive, use this
                // instead of the element content.
                if (binding.value) {
                target.textContent = binding.value
            }
            hljs.highlightBlock(target)
        })
        },
        componentUpdated: function (el, binding) {
            // after an update, re-fill the content and then highlight
            let targets = el.querySelectorAll('code')
            targets.forEach((target) => {
                if (binding.value) {
                target.textContent = binding.value
                hljs.highlightBlock(target)
            }
        })
        }
    });

    var newItemApp = new Vue({
        el: '#newItemApp',
        data: {
            test: '1234',
            info: {},
            record: {},
            resource: {},
            type: '',
            payload: {},
            selected: '',
            deleted: '',
            editorHasErrors: false,
            fieldDisplayName: null,
            resourceItem: null,
            resourceEditUrl: '',
            validationResults: {},
            displayAddItemForm: null,
            newItemSchema: null,
            newItemInput: {},
            status: 'loading',
            formSubmitted: null,
            editorStatus: null,
            savedItem: {},
            quillOptions: {
                modules: {
                }
            }
        },

        methods: {
            onEditorReady(){},
            onEditorFocus(){},
            ready(){},
            editJson(fieldInput){
                this.fieldInput = JSON.parse(fieldInput);
            },
            hasConditions(data, fieldConditions) {
                var status = true;
                if (fieldConditions != null) {
                    if (eval(fieldConditions)) {
                        status = true;
                    }
                    else {
                        status = false;
                    }
                }

                return status;
            },
            changed(input){
                this.appStatus = 'editing';
                if(input == true){
                    this.formSubmitted = true;
                }
                if(this.formSubmitted != null) {
                    var payload = {};

                    payload = this.newItemInput;
                    console.log(payload);
                    this.payload = payload;
                    url = '/api/resources/' + this.type;
                    payload = {data: payload, validate: true};
                    console.log('Payload:');
                    console.log(payload);
                    axiosConfig = {
                        headers: {
                            'Content-Type': 'application/json',
                            'Cache-Control': 'no-cache',
                            "Access-Control-Allow-Origin": "*"
                        }
                    };
                    axios({
                        method: 'post',
                        url: url,
                        headers: axiosConfig,
                        data: payload
                    }).then(response => (this.validationResults = response.data)
                )
                    ;
                    console.log('Server recieved:');
                    console.log(this.info);
                }
            },
            updateSavedItem(data){
                this.savedItem = data;
                this.status = 'success';
            },
            save(){
                this.status = 'loading';
                console.log('Saving...');
                var payload = {};

                payload = this.newItemInput;
                console.log(payload);
                this.payload = payload;
                url = '/api/resources/' + this.type;
                payload = {data: payload, save: true};
                console.log('Payload:');
                console.log(payload);
                axiosConfig = {
                    headers: {
                        'Content-Type': 'application/json',
                        'Cache-Control': 'no-cache',
                        "Access-Control-Allow-Origin": "*"
                    }
                };
                axios({
                    method: 'post',
                    url: url,
                    headers: axiosConfig,
                    data: payload
                }).then(response => (this.updateSavedItem(response.data)));
                console.log('Server recieved:');
                console.log(this.savedItem);
            },
            updateStatus(status){
                this.editorStatus = status;
            },
            updateType(input){
                this.type = input;
            },
            updateNewItem(input){
                this.info = input;
                for (var field in input.data.schema.fields) {
                    this.newItemInput[field] = '';
                }
            },
            newItem(options){
                this.type = options.type;
                var config = {
                    headers: {
                        'Content-Type': 'application/json',
                        'Cache-Control': 'no-cache'
                    },
                    data: {}
                };
                var url = '/api/resources/' + this.type;
                axios
                    .post(url, config)
                    .then(response => (this.updateNewItem(response.data)));
            },
            sectionHasValidations(section){
                console.log(section.fields);
                var result = Object.values(section.fields).some(
                    function(field) {
                        console.log(field);
                        return (field.hasOwnProperty('validations') && field.validations.hasOwnProperty('required') && field.validations.required);
                    }
                );
                console.log(result);
                return result;
            }
        },
        mounted () {
            this.status = 'loaded';
            console.log('1234');
        }

    });
</script>