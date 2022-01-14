<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Reset password - Water Billing System</title>
    <link href="{{ asset('img/favicon.ico') }}" rel="shortcut icon" type="image/vnd.microsoft.icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/uikit/3.0.0-rc.8/css/uikit.css">
    <style>
        .danger{
            color: red;
        }
        .uk-button-primary {
            background-color: #FF7643;
        }
        .uk-button-primary:hover{
            background-color: #dd4f1b;
        }
        .uk-input:active, .uk-input:focus{
            border-color: #FF7643;
        }
    </style>
</head>
<body>
    <div class="uk-section uk-section-muted uk-flex uk-flex-middle uk-animation-fade" uk-height-viewport>
        <div class="uk-width-1-1">
            <div class="uk-container">
                <div class="uk-grid-margin uk-grid uk-grid-stack" uk-grid>
                    <div class="uk-width-1-1@m">
                        <div class="uk-margin uk-width-large uk-margin-auto uk-card uk-card-default uk-card-body uk-box-shadow-large">
                            <h3 class="uk-card-title uk-text-center">Reset password</h3>
                            <form method="POST" action="{{ route('password.update') }}">
                                {{ csrf_field() }}
                                <input type="hidden" name="token" value="{{ $token }}">
                                <input type="hidden" name="email" value="{{ $email }}">
                                <div class="uk-margin">
                                    <div class="uk-inline uk-width-1-1">
                                        <span class="uk-form-icon" uk-icon="icon: lock"></span>
                                        <input class="uk-input uk-form-large @error('password') uk-form-danger @enderror" name="password" type="password" placeholder="New Password">	
                                    </div>
                                    @error('email')
                                        <small class="danger">{{ $message }}</small>
                                    @enderror
                                    @error('password')
                                        <small class="danger">{{ $message }}</small>
                                    @enderror
                                </div>
                                <div class="uk-margin">
                                    <div class="uk-inline uk-width-1-1">
                                        <span class="uk-form-icon" uk-icon="icon: lock"></span>
                                        <input class="uk-input uk-form-large @error('password_confirmation') uk-form-danger @enderror" name="password_confirmation" type="password" placeholder="Confirm password">	
                                    </div>
                                    @error('password_confirmation')
                                        <small class="danger">{{ $message }}</small>
                                    @enderror
                                </div>
                                <div class="uk-margin">
                                    <button class="uk-button uk-button-primary uk-button-large uk-width-1-1">Reset</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/uikit/3.0.0-rc.8/js/uikit.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/uikit/3.0.0-rc.8/js/uikit-icons.js"></script>
</body>
</html>