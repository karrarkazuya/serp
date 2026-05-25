# -*- coding: utf-8 -*-
from collections import OrderedDict
import base64
from odoo import http, fields
from odoo.exceptions import ValidationError, UserError

from odoo.osv import expression
from odoo.addons.portal.controllers.portal import CustomerPortal, pager as portal_pager
from odoo.http import request
from werkzeug.utils import redirect
from datetime import datetime, timedelta, date
from pytz import timezone
import json
import math

class planned_days_controller(http.Controller):

    @http.route('/sshr/planned_days_scheduler_employee', auth='user', website=True)
    def planned_data_view(self, department_id=0, **kw):
        
        try:
            # get selected company
            # 1️⃣ Read cids from cookie (SOURCE OF TRUTH)
            cids = request.httprequest.cookies.get('cids')

            if cids:
                company_ids = [int(cid) for cid in cids.split(',')]
            else:
                company_ids = [request.env.user.company_id.id]

            # 2️⃣ Rebuild environment with correct company context
            env = request.env(context=dict(
                request.env.context,
                allowed_company_ids=company_ids,
            ))

            company = env.company
            companies = env.companies
            selected_company_id = company.id
        except:
            selected_company_id = 0
        
        if selected_company_id != 0:
            departments_records = request.env['hr.department'].search([('company_id', '=', company.id)])
        else:
            departments_records = request.env['hr.department'].search([])
        employees = []
        if department_id != 0:
            employees = request.env['hr.employee'].search([('department_id', 'child_of', int(department_id))])
            
        working_schedules_can_use = request.env['resource.calendar'].search([])
        
        days = []
        today = date.today()
        for i in range(30):
            if i == 0:
                continue
            d = today + timedelta(days=i)
            days.append(d.strftime("%Y-%m-%d"))
            
        working_schedules_can_use_data = []
        for item in working_schedules_can_use:
            working_schedules_can_use_data.append({
                "id": item.id,
                "title": item.name
            })
            
        departments = []
        for item in departments_records:
            departments.append({
                "id": item.id,
                "title": item.name
            })
            
        employee_data = []
        for employee in employees:
            planned_days_records = request.env['jtemployees.planned.days'].sudo().search([('employee_id', '=', employee.id)])
            planned_days = []
            
            for day in days:
                added_to_filtered = False
                for planned_day in planned_days_records:
                    planned_day_date = planned_day.date.strftime("%Y-%m-%d")
                    if planned_day_date == day:
                        new_planned_day = self.prepare_day(planned_day.id, planned_day_date, planned_day.schedule_id, working_schedules_can_use_data.copy())
                        planned_days.append(new_planned_day)
                        added_to_filtered = True
                        break
                    
                if not added_to_filtered:
                    planned_day = self.prepare_day(0, day, False, working_schedules_can_use_data.copy())
                    planned_days.append(planned_day)
                    
              
            employee_data.append({
                "id": employee.id,
                "name": employee.name,
                "days": planned_days
            })
            
        result = {'employee_data': employee_data, 'available_schedules': working_schedules_can_use_data, 'days': days, 'departments': departments, 'selected_department': department_id}
        
        return request.render('jtemployees.planned_working_schedules_gantt', result)
    
    @http.route('/sshr/planned_days_scheduler_sub_employee', auth='user', website=True)
    def planned_data_sub_view(self, **kw):
        employee = request.env.user.employee_id
        
        sub_employees = []
        if employee.jt_can_set_sub_schedule:
            sub_employees = request.env['jtemployees.subs'].search([('parent_employee_ids', 'in', [employee.id])])
        working_schedules_can_use = request.env['resource.calendar'].search([])
        
        days = []
        today = date.today()
        for i in range(30):
            if i == 0:
                continue
            d = today + timedelta(days=i)
            days.append(d.strftime("%Y-%m-%d"))
            
        working_schedules_can_use_data = []
        for item in working_schedules_can_use:
            working_schedules_can_use_data.append({
                "id": item.id,
                "title": item.name
            })
            
        employee_data = []
        for sub_employee in sub_employees:
            planned_days_records = request.env['jtemployees.planned.days'].sudo().search([('employee_id', '=', sub_employee.employee_id.id)])
            planned_days = []
            
            for day in days:
                added_to_filtered = False
                for planned_day in planned_days_records:
                    planned_day_date = planned_day.date.strftime("%Y-%m-%d")
                    if planned_day_date == day:
                        new_planned_day = self.prepare_day(planned_day.id, planned_day_date, planned_day.schedule_id, working_schedules_can_use_data.copy())
                        planned_days.append(new_planned_day)
                        added_to_filtered = True
                        break
                    
                if not added_to_filtered:
                    planned_day = self.prepare_day(0, day, False, working_schedules_can_use_data.copy())
                    planned_days.append(planned_day)
                    
              
            employee_data.append({
                "id": sub_employee.employee_id.id,
                "name": sub_employee.employee_id.name,
                "days": planned_days
            })
            
        result = {'employee_data': employee_data, 'available_schedules': working_schedules_can_use_data, 'days': days}
        
        return request.render('jtemployees.sub_planned_working_schedules_gantt', result)
    
    @http.route('/sshr/planned_days_sub_employee_submit', auth='user')
    def planned_data_submit(self, type="", data="", **kw):
        employee = request.env.user.employee_id
        
        """
        records = request.env["jtemployees.location.areas"].search([('id', '=', record_id)], limit=1)
        records[0].write({
            "area_data": data
        })
        """
        print(data)
        data = json.loads(data)
        if type == "create_scheme":
        
            selected_employee_id = data['employee_id']
            
            days = data['day_ids']
            days.sort()
            count = len(days)
            last = days[count - 1]
            
            print(employee.id)
            print(selected_employee_id)
            
            sub_employees = request.env['jtemployees.subs'].search([('parent_employee_ids', 'in', [employee.id]), ('employee_id', '=', selected_employee_id)], limit=1)
            
            if sub_employees:
                selected_day = request.env['jtemployees.planned.days'].sudo().search([('employee_id', '=', selected_employee_id), ('id', '=', last)], limit=1)
            else:
                selected_day = request.env['jtemployees.planned.days'].search([('employee_id', '=', selected_employee_id), ('id', '=', last)], limit=1)
                
            if selected_day:
                if count == 2:
                    selected_day.replicate_previous_day_for_rest()
                if count == 3:
                    selected_day.replicate_previous_two_days_for_rest()
                if count == 4:
                    selected_day.replicate_previous_three_days_for_rest()
                if count == 5:
                    selected_day.replicate_previous_four_days_for_rest()
                if count == 6:
                    selected_day.replicate_previous_five_days_for_rest()
                if count == 7:
                    selected_day.replicate_previous_six_days_for_rest()
            
        if type == "schedule_changes":
            for changeData in data:
                day_id = changeData['day_id']
                selected_employee_id = changeData['employee_id']
                working_schedule_id = changeData['working_schedule_id']
                
                sub_employees = request.env['jtemployees.subs'].search([('parent_employee_ids', 'in', [employee.id]), ('employee_id', '=', selected_employee_id)], limit=1)
                if sub_employees:
                    selected_day = request.env['jtemployees.planned.days'].sudo().search([('employee_id', '=', selected_employee_id), ('id', '=', day_id)], limit=1)
                else:
                    selected_day = request.env['jtemployees.planned.days'].search([('employee_id', '=', selected_employee_id), ('id', '=', day_id)], limit=1)
                    
                if selected_day:
                    selected_day.schedule_id = working_schedule_id
        return self.response_json({"status": "success"})
    
    
    # mobile API
    @http.route('/jt_api/hr/planned_days_scheduler_employee', type='http', auth='none', methods=['GET'], csrf=False)
    def planned_data_sub_view_mobile(self, **kw):
        try:
            auth_user = self.get_user(request)
            
            if not auth_user:
                return self.response_json({'status': 'error', 'code': 1, 'message': 'auth required'})
            request.env = request.env(user=auth_user.user_id.id)
            

            employee = request.env['hr.employee'].sudo().search([('id', '=', auth_user.employee.id)], limit=1)
            
            childs = self.get_all_childs(request=request, employee_id=employee.id)
                
            sub_employees = request.env['hr.employee'].sudo().search([('id', 'in', childs)])
                
            working_schedules_can_use = request.env['resource.calendar'].search([])
            
            days = []
            today = date.today()
            for i in range(30):
                if i == 0:
                    continue
                d = today + timedelta(days=i)
                days.append(d.strftime("%Y-%m-%d"))
                
            working_schedules_can_use_data = []
            for item in working_schedules_can_use:
                working_schedules_can_use_data.append({
                    "id": item.id,
                    "title": item.name
                })
                
            employee_data = []
            for sub_employee in sub_employees:
                planned_days_records = request.env['jtemployees.planned.days'].sudo().search([('employee_id', '=', sub_employee.id)])
                planned_days = []
                
                for day in days:
                    added_to_filtered = False
                    for planned_day in planned_days_records:
                        planned_day_date = planned_day.date.strftime("%Y-%m-%d")
                        if planned_day_date == day:
                            new_planned_day = self.prepare_day(planned_day.id, planned_day_date, planned_day.schedule_id, working_schedules_can_use_data.copy())
                            new_planned_day['available_schedules'] = False
                            planned_days.append(new_planned_day)
                            added_to_filtered = True
                            break
                        
                    if not added_to_filtered:
                        planned_day = self.prepare_day(0, day, False, working_schedules_can_use_data.copy())
                        planned_day['available_schedules'] = False
                        planned_days.append(planned_day)
                        
                
                employee_data.append({
                    "id": sub_employee.id,
                    "name": sub_employee.name,
                    "days": planned_days
                })
                
            results = {'employee_data': employee_data, 'available_schedules': working_schedules_can_use_data, 'days': days}
            response = {
                'status': 'success',
                'data': results
            }
            
            return self.response_json(response)
        except:
            return self.response_json({'status': 'error', 'code':2, 'message': 'Unable to process the request'})

    @http.route('/jt_api/hr/planned_days_sub_employee_submit', type='http', auth='none', methods=['GET'], csrf=False)
    def planned_data_submit_mobile(self, type="", data="", **kw):
        auth_user = self.get_user(request)
            
        if not auth_user:
            return self.response_json({'status': 'error', 'code': 1, 'message': 'auth required'})
        request.env = request.env(user=auth_user.user_id.id)
        

        employee = request.env['hr.employee'].sudo().search([('id', '=', auth_user.employee.id)], limit=1)
        
        childs = self.get_all_childs(request=request, employee_id=employee.id)
        
        data = json.loads(data)
        if type == "create_scheme":
        
            selected_employee_id = data['employee_id']
            
            if selected_employee_id not in childs:
                return self.response_json({'status': 'error', "code": 14, 'message': 'You do not have permission for this employee'})
            
            days = data['day_ids']
            days.sort()
            count = len(days)
            last = days[count - 1]
            
            sub_employees = request.env['jtemployees.subs'].search([('parent_employee_ids', 'in', [employee.id]), ('employee_id', '=', selected_employee_id)], limit=1)
            
            if sub_employees:
                selected_day = request.env['jtemployees.planned.days'].sudo().search([('employee_id', '=', selected_employee_id), ('id', '=', last)], limit=1)
            else:
                selected_day = request.env['jtemployees.planned.days'].search([('employee_id', '=', selected_employee_id), ('id', '=', last)], limit=1)
                
            if selected_day:
                if count == 2:
                    selected_day.replicate_previous_day_for_rest()
                if count == 3:
                    selected_day.replicate_previous_two_days_for_rest()
                if count == 4:
                    selected_day.replicate_previous_three_days_for_rest()
                if count == 5:
                    selected_day.replicate_previous_four_days_for_rest()
                if count == 6:
                    selected_day.replicate_previous_five_days_for_rest()
                if count == 7:
                    selected_day.replicate_previous_six_days_for_rest()
            
        if type == "schedule_changes":
            for changeData in data:
                day_id = changeData['day_id']
                selected_employee_id = changeData['employee_id']
                if selected_employee_id not in childs:
                    return self.response_json({'status': 'error', "code": 14, 'message': 'You do not have permission for this employee'})
                working_schedule_id = changeData['working_schedule_id']
                
                selected_day = request.env['jtemployees.planned.days'].sudo().search([('employee_id', '=', selected_employee_id), ('id', '=', day_id)], limit=1)
                    
                if selected_day:
                    selected_day.schedule_id = working_schedule_id
        return self.response_json({"status": "success"})
    
    
    def prepare_day(self, day_id, date, planned_day_schedule, available_schedules):
        
        working_schedule_id = 0
        working_schedule_title = ''
        if planned_day_schedule:
            working_schedule_id = planned_day_schedule.id
            working_schedule_title = planned_day_schedule.name
            add_missing = True
            for item in available_schedules:
                if item['id'] == planned_day_schedule.id:
                    add_missing = False
                    break
            
            if add_missing:
                available_schedules.append({
                    "id": planned_day_schedule.id,
                    "title": planned_day_schedule.name
                })
                
        return {
            "id": day_id,
            "working_schedule_id": working_schedule_id,
            "working_schedule_title": working_schedule_title,
            'available_schedules': available_schedules,
            "date": date
        }
    
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
    
    def get_all_childs(self, request, employee_id, visited=None):
        if visited is None:
            visited = set()
        
        if employee_id in visited:
            return []
        visited.add(employee_id)
        
        ids = []
        employees = request.env['hr.employee'].sudo().search([('parent_id', '=', employee_id)])
        for item in employees:
            ids.append(item.id)
            new_ids = self.get_all_childs(request=request, employee_id=item.id, visited=visited)
            for item2 in new_ids:
                ids.append(item2)
        return ids
      
    
    def get_user(self, request):
        if 'Authorization' not in request.httprequest.headers:
            return False
                
        token = request.httprequest.headers['Authorization']
        return request.env['jtapi.users'].sudo().auth_user(token, ['hr'])
