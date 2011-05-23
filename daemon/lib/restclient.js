var util = require('util');
var events = require('events');

function RESTClient(rest_server_url){
    this.rest_server_url = rest_server_url;
}

util.inherits(RESTClient, events.EventEmitter);

RESTClient.prototype.get = function(){
    this.method = "GET";
    return this._execute();
};

RESTClient.prototype.create = function(data){
    this.method = "POST";
    this.data   = data;
    return this._execute();
};

RESTClient.prototype.update = function(data){
    this.method = "PUT";
    this.data   = data;
    return this._execute();
};

RESTClient.prototype.del = function(){
    this.method = "DELETE";
    return this._execute();
};

RESTClient.prototype.resource = function(resource){
    this.resource = resource;
    return this;
};

RESTClient.prototype.identifiers = function(ids){
    if (typeof ids != 'object'){
        ids = [ids];
    }
    this.ids = ids;
    return this;
};

RESTClient.prototype._execute = function(){

    console.log('this.ids', this.ids);

    this.rest_server_url += this.resource + '/' + ((this.ids) ? this.ids.join(',') : '');

    console.log('this.rest_server_url', this.rest_server_url);

    var parsed_url = require('url').parse(this.rest_server_url);

    var headers = {
        "connection" : "close"
    };

    if ((this.method == "POST" || this.method == "PUT") && this.data){

        this.data = require('querystring').stringify(this.data);

        headers['content-type']   = "application/x-www-form-urlencoded";
        headers['content-length'] = this.data.length;
    }

    var options = {
        host:    parsed_url.hostname,
        port:    parsed_url.port,
        path:    parsed_url.pathname + (parsed_url.search || ''),
        method:  this.method,
        headers: headers
    };

    console.log(options, this.data);

    var http = require('http');
    var self = this;
    var body = '';

    var req = http.request(options, function(res){
        console.log('STATUS:', res.statusCode);
        console.log('HEADERS:', res.headers);
        res.setEncoding('utf8');
        res.on('data', function (chunk) {
            console.log('BODY: ' + chunk);
            body += chunk.toString();
        });

        res.on('end', function(){

            try{
                body = JSON.parse(body);
            }catch(e){
                var error = "Result cannot be decoded";
            }

            if (body.status != "OK"){
                error = body.error ? body.error : "No description of the error";
            }

            self.emit('end', body, error);
        })
    });

    if (this.data){
        req.write(this.data + '\n');
    }
    
    req.end();
    return this;
};

module.exports.RESTClient = RESTClient;
