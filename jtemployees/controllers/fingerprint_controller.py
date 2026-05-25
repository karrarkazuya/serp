# -*- coding: utf-8 -*-
from odoo import http, fields
from odoo.exceptions import ValidationError, UserError

from odoo.addons.portal.controllers.portal import CustomerPortal, pager as portal_pager
from odoo.http import request
import json
from odoo.tools import config

class fingerprint_controller(http.Controller):

    @http.route('/jt_api/hr/fingerprint/create', type='http', auth='none', methods=['POST'], csrf=False)
    def create_fingerprint_record(self, **kw):
        validate_request = self.validate_request(request)
            
        if not validate_request:
            return self.response_json({'status': 'error', 'code': 1, 'message': 'auth required'})
        
        user_id               = kw.get('user_id')
        record_date           = kw.get('record_date')
        check_in              = kw.get('check_in')
        check_out             = kw.get('check_out')
        data_reference        = kw.get('data_reference')
        
        
        payload = {
                "user_id": user_id,
                "record_date": record_date,
                "check_in": check_in,
                "check_out": check_out,
                "data_reference": data_reference
            }
        
        
        request.env['jtemployees.fingerprint'].sudo().create(payload)
        
        return self.response_json({"result": "success"})
        try:
            validate_request = self.validate_request(request)
            
            if not validate_request:
                return self.response_json({'status': 'error', 'code': 1, 'message': 'auth required'})
            
            user_id               = kw.get('user_id')
            record_date           = kw.get('record_date')
            check_in              = kw.get('check_in')
            check_out             = kw.get('check_out')
            data_reference        = kw.get('data_reference')
            
            
            payload = {
                    "user_id": user_id,
                    "record_date": record_date,
                    "check_in": check_in,
                    "check_out": check_out,
                    "data_reference": data_reference
                }
            
            
            request.env['jtemployees.fingerprint'].sudo().create(payload)
            
            return self.response_json({"result": "success"})
        except (ValidationError, UserError) as e:
            return self.response_json({
                'status': 'error',
                'code':4,
                'message': str(e)
            })
        except:
            return self.response_json({'status': 'error', 'code':2, 'message': 'Unable to process the request'})
        
    def response_json(self, responseData):
        response = http.Response(
                json.dumps(responseData, default=str),
                status=200,
                mimetype='application/json'
            )
        response.headers['Access-Control-Allow-Origin'] = '*'
        response.headers['Access-Control-Allow-Methods'] = 'GET, POST, OPTIONS, PUT, DELETE'
        response.headers['Access-Control-Allow-Headers'] = 'Content-Type, Authorization, Origin, X-Requested-With, Accept'
        return response
    
    def validate_request(self, request):
        if 'Authorization' not in request.httprequest.headers:
            return False
                
        token = request.httprequest.headers['Authorization']
        return token == config.get('fingerprint_token')
