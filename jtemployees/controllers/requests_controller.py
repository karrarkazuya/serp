# -*- coding: utf-8 -*-
from collections import OrderedDict
import base64
from odoo import http, fields
from odoo.exceptions import ValidationError, UserError

from odoo.osv import expression
from odoo.addons.portal.controllers.portal import CustomerPortal, pager as portal_pager
from odoo.http import request
from werkzeug.utils import redirect
from datetime import datetime, timedelta
from pytz import timezone
import json
import math

class requests_controller(http.Controller):

    # requests
    @http.route('/jt_api/hr/requests/index/<int:employee_id>/page/<int:page>', type='http', auth='none', methods=['GET'], csrf=False)
    def requests_history(self, employee_id=0, page=1, **kw):
        try:
            auth_user = self.get_user(request)
            
            if not auth_user:
                return self.response_json({'status': 'error', 'code': 1, 'message': 'auth required'})
            request.env = request.env(user=auth_user.user_id.id)
            
            if page < 1:
                page = 1
            employee = request.env['hr.employee'].sudo().search([('id', '=', auth_user.employee.id)], limit=1)

            page_size = 25
            start = (page - 1) * page_size
            end = start + page_size
            

            if employee_id != employee.id:
                employee = request.env['hr.employee'].sudo().browse([employee_id])

            payload = [( "employee_id", "=", employee.id), ("deleted", "=", False)]
            
            if auth_user.employee.id != employee.id:
                payload.append(("parent_id", "=", auth_user.employee.id))
            
            requests = request.env['jtemployees.requests'].sudo().search(payload, 
                                                                        limit=page_size, 
                                                                        offset=start, 
                                                                        order='id desc')
            
            requests_count = request.env['jtemployees.requests'].sudo().search_count(payload)
            last_page = math.ceil(requests_count / page_size)
            
            results = requests.read(fields=["id", "name", "request_type", "manager_approved", "hr_approved", "datetime_from", "datetime_to", "date_from", "date_to", "write_uid", "create_date"])
            response = {
                'status': 'success',
                'data': results,
                'current_page': page,
                'last_page': last_page
            }
            
            return self.response_json(response)
        except:
            return self.response_json({'status': 'error', 'code':2, 'message': 'Unable to process the request'})

    @http.route('/jt_api/hr/requests_all/index/page/<int:page>', type='http', auth='none', methods=['GET'], csrf=False)
    def requests_all_history(self, page=1, **kw):
        try:
            auth_user = self.get_user(request)
            
            if not auth_user:
                return self.response_json({'status': 'error', 'code': 1, 'message': 'auth required'})
            request.env = request.env(user=auth_user.user_id.id)
            
            if page < 1:
                page = 1
            employee = request.env['hr.employee'].sudo().search([('id', '=', auth_user.employee.id)], limit=1)

            page_size = 25
            start = (page - 1) * page_size
            end = start + page_size
            
            payload = [("deleted", "=", False), ("parent_id", "=", employee.id)]
            
            requests = request.env['jtemployees.requests'].sudo().search(payload, 
                                                                        limit=page_size, 
                                                                        offset=start, 
                                                                        order='id desc')
            
            requests_count = request.env['jtemployees.requests'].sudo().search_count(payload)
            last_page = math.ceil(requests_count / page_size)
            
            results = requests.read(fields=["id", "name", "request_type", "manager_approved", "hr_approved", "datetime_from", "datetime_to", "date_from", "date_to", "write_uid", "create_date"])
            response = {
                'status': 'success',
                'data': results,
                'current_page': page,
                'last_page': last_page
            }
            
            return self.response_json(response)
        except:
            return self.response_json({'status': 'error', 'code':2, 'message': 'Unable to process the request'})

    @http.route('/jt_api/hr/requests/view/<int:request_id>', type='http', auth='none', methods=['GET'], csrf=False)
    def requests_view(self, request_id=0, **kw):
        try:
            auth_user = self.get_user(request)
            
            if not auth_user:
                return self.response_json({'status': 'error', 'code': 1, 'message': 'auth required'})
            request.env = request.env(user=auth_user.user_id.id)
            
            employee = request.env['hr.employee'].sudo().search([('id', '=', auth_user.employee.id)], limit=1)
            
            payload = [( "id", "=", request_id), ("deleted", "=", False), '|', ("employee_id", "=", employee.id), ("parent_id", "=", employee.id)]

            requests = request.env['jtemployees.requests'].sudo().search(payload, limit=1)
           
            results = requests.read(fields=["id", "parent_id", "employee_id", "name", "days_requested", "minutes_requested", "request_type", "manager_approved", "hr_approved", "datetime_from", "datetime_to", "date_from", "date_to", "manager_note", "hr_note", "create_date"])
            
            index = 0
            for item in results:
                # Prepare the image
                image = requests[index].extra_image
                if image:
                    # Convert binary image to base64
                    image = "data:image/png;base64," + image.decode()
                else:
                    image = None  # Fallback if no image is available
                    
                item['image'] = image
                index += 1
            response = {
                'status': 'success',
                'data': results[0]
            }
            
            return self.response_json(response)
        except:
            return self.response_json({'status': 'error', 'code':2, 'message': 'Unable to process the request'})

    @http.route('/jt_api/hr/resources', type='http', auth='none', methods=['GET'], csrf=False)
    def resources(self):
        return self.response_json({
            "day_request_types": [
                {
                    "key": "unpaid_leave",
                    "title": "Unpaid Leave",
                    "description": "Salary deduction made",
                    "title_ar": "إجازة بدون راتب",
                    "description_ar": "يستقطع من الراتب"
                },
                {
                    "key": "paid_leave",
                    "title": "Normal Leave",
                    "description": "Balance deduction made",
                    "title_ar": "إجازة اعتيادية",
                    "description_ar": "يستقطع من الرصيد"
                },
                {
                    "key": "admin_leave",
                    "title": "Admin Leave",
                    "description": "No balance deduction made",
                    "title_ar": "إجازة خاصة",
                    "description_ar": "لا يوجد استقطاع"
                },
                {
                    "key": "remote_working_leave",
                    "title": "Work From Home Leave",
                    "description": "Working remotely from home, No balance deduction made",
                    "title_ar": "إجازة العمل من المنزل",
                    "description_ar": "العمل عن بُعد من المنزل، لا يوجد استقطاع من الرصيد"
                },
                {
                    "key": "field_work_leave",
                    "title": "Field Work Leave",
                    "description": "Working at a client site, branch, or field location, No balance deduction made",
                    "title_ar": "إجازة العمل الميداني",
                    "description_ar": "العمل في موقع عميل أو فرع أو موقع ميداني، لا يوجد استقطاع من الرصيد"
                },
                {
                    "key": "official_mission_leave",
                    "title": "Official Mission Leave",
                    "description": "An official task outside the office, No balance deduction made",
                    "title_ar": "إجازة مهمة رسمية",
                    "description_ar": "مهمة رسمية خارج المكتب، لا يوجد استقطاع من الرصيد"
                },
                {
                    "key": "external_training_leave",
                    "title": "External Training Leave",
                    "description": "Attending a course or workshop outside the office, No balance deduction made",
                    "title_ar": "إجازة تدريب خارجي",
                    "description_ar": "حضور دورة أو ورشة عمل خارج المكتب، لا يوجد استقطاع من الرصيد"
                },
                {
                    "key": "client_visit_leave",
                    "title": "Client Visit Leave",
                    "description": "Meeting clients at their location, No balance deduction made",
                    "title_ar": "إجازة زيارة عميل",
                    "description_ar": "الاجتماع بالعملاء في موقعهم، لا يوجد استقطاع من الرصيد"
                },
                {
                    "key": "government_errand_leave",
                    "title": "Government Errand Leave",
                    "description": "Going to a ministry, bank, or official office on company business, No balance deduction made",
                    "title_ar": "إجازة مراجعة حكومية",
                    "description_ar": "التوجه إلى وزارة أو بنك أو جهة رسمية لأعمال الشركة، لا يوجد استقطاع من الرصيد"
                }
            ],
        "time_off_request_types": [
            {
                "key": "admin_time_off",
                "title": "Admin Time Off",
                "description": "No balance deduction made",
                "title_ar": "إجازة وقية خاصة",
                "description_ar": "لا يوجد استقطاع"
            },
            {
                "key": "paid_time_off",
                "title": "Normal Time Off",
                "description": "Balance deduction made",
                "title_ar": "إجازة وقتية اعتيادية",
                "description_ar": "يستقطع من الرصيد"
            },
            {
                "key": "unpaid_time_off",
                "title": "Unpaid Time Off",
                "description": "Salary deduction made",
                "title_ar": "إجازة وقتية بدون راتب",
                "description_ar": "يستقطع من الراتب"
            },
            {
                "key": "field_work_time_off",
                "title": "Field Work Time Off",
                "description": "Working at a client site, branch, or field location, No balance deduction made",
                "title_ar": "إجازة وقتية للعمل الميداني",
                "description_ar": "العمل في موقع عميل أو فرع أو موقع ميداني، لا يوجد استقطاع من الرصيد"
            },
            {
                "key": "official_mission_time_off",
                "title": "Official Mission Time Off",
                "description": "An official task outside the office, No balance deduction made",
                "title_ar": "إجازة وقتية للمهام الرسمية",
                "description_ar": "مهمة رسمية خارج المكتب، لا يوجد استقطاع من الرصيد"
            },
            {
                "key": "external_training_time_off",
                "title": "External Training Time Off",
                "description": "Attending a course or workshop outside the office, No balance deduction made",
                "title_ar": "إجازة وقتية للدورات الخارجية",
                "description_ar": "حضور دورة أو ورشة عمل خارج المكتب، لا يوجد استقطاع من الرصيد"
            },
            {
                "key": "client_visit_time_off",
                "title": "Client Visit Time Off",
                "description": "Meeting clients at their location, No balance deduction made",
                "title_ar": "إجازة وقتية لزيارة عميل",
                "description_ar": "الاجتماع بالعملاء في موقعهم، لا يوجد استقطاع من الرصيد"
            },
            {
                "key": "government_errand_time_off",
                "title": "Government Errand Time Off",
                "description": "Going to a ministry, bank, or official office on company business, No balance deduction made",
                "title_ar": "إجازة وقتية للمراجعة الحكومية",
                "description_ar": "التوجه إلى وزارة أو بنك أو جهة رسمية لأعمال الشركة، لا يوجد استقطاع من الرصيد"
            }
        ],
            "overtime_request_types": [
                {
                    "key": "over_time",
                    "title": "Over Time",
                    "description": "No balance deduction made",
                    "title_ar": "العمل الإضافي",
                    "description_ar": "لا يوجد استقطاع"
                }
            ]
        })
        
    @http.route('/jt_api/hr/requests/create', type='http', auth='none', methods=['POST'], csrf=False)
    def create_request(self, **kw):
        try:
            auth_user = self.get_user(request)
            
            if not auth_user:
                return self.response_json({'status': 'error', 'code': 1, 'message': 'auth required'})
            request.env = request.env(user=auth_user.user_id.id)
            
            user = auth_user.user_id
            
            description    = kw.get('description')
            request_type   = kw.get('request_type')
            date_from      = kw.get('date_from')
            date_to        = kw.get('date_to')
            
            
            payload = {
                    "name": description,
                    "request_type": request_type,
                }
            
            image          = request.httprequest.files.get('image')
            
            if image:
                image = base64.b64encode(image.read())
                payload['extra_image'] = image
            
            if request_type in ["admin_leave", "unpaid_leave", "paid_leave", "remote_working_leave", "field_work_leave", "official_mission_leave", "external_training_leave", "client_visit_leave", "government_errand_leave"]:
                payload['request_period_type'] = 'leave'
                payload['date_from'] = date_from
                payload['date_to']   = date_to
            elif request_type in ["admin_time_off", "paid_time_off", "unpaid_time_off", "over_time", "field_work_time_off", "official_mission_time_off", "external_training_time_off", "client_visit_time_off", "government_errand_time_off"]:
                payload['datetime_from'] = date_from
                payload['datetime_to']   = date_to
                payload['request_period_type'] = 'timeoff'
                
            if request_type in ["over_time"]:
                payload['request_period_type'] = 'overtime'

            
            request_made = request.env['jtemployees.requests'].with_user(user).sudo().create(payload)
            result = request_made.read(fields=["id", "parent_id", "employee_id", "name", "days_requested", "minutes_requested", "request_type", "manager_approved", "hr_approved", "datetime_from", "datetime_to", "date_from", "date_to", "extra_image", "create_date"])
            
            # Prepare the image
            image = request_made.extra_image
            if image:
                # Convert binary image to base64
                image = "data:image/png;base64," + image.decode()
            else:
                image = None  # Fallback if no image is available
                
            result[0]['image'] = image
            
            return self.response_json(result)
        except (ValidationError, UserError) as e:
            return self.response_json({
                'status': 'error',
                'code':4,
                'message': str(e)
            })
        except:
            return self.response_json({'status': 'error', 'code':2, 'message': 'Unable to process the request'})
        
    @http.route('/jt_api/hr/requests/confirm', type='http', auth='none', methods=['POST'], csrf=False)
    def confirm_request(self):
        try:
            auth_user = self.get_user(request)
            
            if not auth_user:
                return self.response_json({'status': 'error', 'code': 1, 'message': 'auth required'})
            request.env = request.env(user=auth_user.user_id.id)
            
            user = auth_user.user_id
            
            employee = request.env['hr.employee'].sudo().search([('id', '=', auth_user.employee.id)], limit=1)
            
            params           = request.get_json_data()
            request_id       = params['request_id']
            manager_approved = params['state'] # approved, rejected
            manager_note     = params['note']
            
            if manager_approved not in ['approved', 'rejected']:
                return self.response_json({'status': 'error', "code": 15, 'message': 'Unsupported state'})
            
            payload = [( "id", "=", request_id), ("deleted", "=", False), ("parent_id", "=", employee.id)]
            
            request_made = request.env['jtemployees.requests'].sudo().search(payload, limit=1)
          
            if not request_made.manager_approved and request_made.manager_approved == 'rejected':
                return self.response_json({'status': 'error', "code": 17, 'message': 'This request has been rejected, you can not modify a rejected request'})

            write_payload = {
                    "manager_approved": manager_approved,
                    "manager_note": manager_note
                }
            
            if request_made.with_user(user).sudo().write(write_payload):
                return self.response_json(request_made.read(fields=["id", "parent_id", "employee_id", "name", "days_requested", "minutes_requested", "request_type", "manager_approved", "hr_approved", "datetime_from", "datetime_to", "date_from", "date_to", "create_date"]))
            return self.response_json({'status': 'error', 'code':2, 'message': 'Unable to process the request'})
        except (ValidationError, UserError) as e:
            return self.response_json({
                'status': 'error',
                'code':4,
                'message': str(e)
            })
        except:
            return self.response_json({'status': 'error', 'code':2, 'message': 'Unable to process the request'})
      
    def get_childs(self, request, employee_id):
        ids = []
        employees = request.env['hr.employee'].sudo().search(['|', ('parent_id', '=', employee_id), ('jt_request_approval', '=', employee_id)])
        for item in employees:
            ids.append(item.id)
        return ids
      
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
    
    def get_user(self, request):
        if 'Authorization' not in request.httprequest.headers:
            return False
                
        token = request.httprequest.headers['Authorization']
        return request.env['jtapi.users'].sudo().auth_user(token, ['hr'])
